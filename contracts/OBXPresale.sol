// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * @title  OBXPresale  v3
 * @notice Multi-chain BEP-20/ERC-20 presale contract for OBXCoin.
 *
 * ─── On-chain visibility (BSCScan / any EVM explorer) ────────────────────
 *  • PhaseAdded / PhaseUpdated events — admin phase management
 *  • USDT Transfer: buyer → treasury (standard ERC-20 Transfer)
 *  • USDT Transfer: buyer → this contract (LP accumulation portion)
 *  • OBX  Transfer: presaleContract → buyer (standard ERC-20 Transfer)
 *  • TokensPurchased: full event linking buyer, phase, amounts, timestamp
 *  • LiquidityAdded: when LP is automatically added to PancakeSwap/Uniswap
 *
 * ─── Auto-Liquidity ──────────────────────────────────────────────────────
 *  Each purchase sets aside `liquidityBps` % of the USDT paid into a pool
 *  held inside this contract.  The corresponding OBX (at the current phase
 *  rate) is also reserved.  When the accumulated USDT crosses
 *  `liquidityThreshold`, the contract automatically calls
 *  router.addLiquidity(), deepening the on-chain OBX/USDT pool.
 *  LP tokens are sent to the treasury wallet.
 *
 *  Set router = address(0) to disable auto-LP (useful before pool creation).
 *
 * ─── Treasury ────────────────────────────────────────────────────────────
 *  Every USDT payment (minus the LP portion) is immediately forwarded to
 *  the treasury address (owner's wallet or multi-sig).
 *
 * ─── Security ────────────────────────────────────────────────────────────
 *  ✓ Reentrancy guard on buyTokens + flushLiquidity
 *  ✓ CEI pattern throughout
 *  ✓ bonusBps capped at MAX_BONUS_BPS (50 %)
 *  ✓ MIN_USDT minimum purchase (10 USDT)
 *  ✓ dbPhaseId > 0 required
 *  ✓ tokenCap >= tokensSold enforced on updatePhase
 *  ✓ Two-step ownership transfer
 *  ✓ Router approval reset to 0 after each LP addition
 *
 * ─── Integration flow ────────────────────────────────────────────────────
 *  Deploy:
 *    1. Deploy OBXToken
 *    2. Deploy OBXPresale(obxToken, usdt, treasury)
 *    3. OBXToken.setFeeExempt(presaleContract, true)
 *    4. OBXToken.transfer(presaleContract, allocation)
 *    5. (Optional) OBXPresale.setRouter(pancakeV2Router)
 *
 *  User:
 *    6. USDT.approve(presaleContract, amount)       ← TX 1
 *    7. OBXPresale.buyTokens(phaseIndex, amount)    ← TX 2
 *       → USDT split: treasury + LP reserve
 *       → OBX sent to buyer immediately
 *       → TokensPurchased emitted
 *       → If LP threshold crossed: LiquidityAdded emitted
 */

// ─── Interfaces ──────────────────────────────────────────────────────────────

interface IERC20 {
    function totalSupply()                                          external view returns (uint256);
    function balanceOf(address account)                            external view returns (uint256);
    function transfer(address to, uint256 amount)                  external returns (bool);
    function allowance(address owner, address spender)             external view returns (uint256);
    function transferFrom(address from, address to, uint256 amount) external returns (bool);
    function approve(address spender, uint256 amount)              external returns (bool);
}

/// @dev Minimal PancakeSwap V2 / Uniswap V2 router interface
interface IUniswapV2Router02 {
    function addLiquidity(
        address tokenA,
        address tokenB,
        uint256 amountADesired,
        uint256 amountBDesired,
        uint256 amountAMin,
        uint256 amountBMin,
        address to,
        uint256 deadline
    ) external returns (uint256 amountA, uint256 amountB, uint256 liquidity);

    function factory() external pure returns (address);
}

// ─── Contract ────────────────────────────────────────────────────────────────

contract OBXPresale {

    // ─── Constants ───────────────────────────────────────────────────────────

    /// @dev Minimum USDT purchase: 10 USDT (USDT uses 6 decimal places)
    uint256 public constant MIN_USDT       = 10_000_000;   // 10 × 10^6
    /// @dev Maximum allowed bonus: 50 %
    uint256 public constant MAX_BONUS_BPS  = 5_000;
    /// @dev Maximum liquidity fee: 10 %
    uint256 public constant MAX_LIQ_BPS    = 1_000;

    // ─── Ownership ───────────────────────────────────────────────────────────

    address public owner;
    address public pendingOwner;

    // ─── Core config ─────────────────────────────────────────────────────────

    address public treasury;         // receives USDT payments & LP tokens
    IERC20  public usdt;             // USDT token on this chain
    IERC20  public obxToken;         // OBXToken held in this contract for sale
    bool    public paused;
    bool    private _locked;         // reentrancy guard

    // ─── Auto-Liquidity ──────────────────────────────────────────────────────

    /// @dev Optional: PancakeSwap/Uniswap V2 router. Zero = LP disabled.
    IUniswapV2Router02 public router;

    /// @dev % of each purchase USDT allocated to LP (basis points). Default 200 = 2 %.
    uint256 public liquidityBps       = 200;

    /// @dev Minimum accumulated USDT to trigger auto-LP addition. Default 100 USDT.
    uint256 public liquidityThreshold = 100_000_000;   // 100 × 10^6

    /// @dev Accumulated USDT inside this contract waiting to be added to LP.
    uint256 public liquidityReserveUsdt;

    /// @dev Accumulated OBX inside this contract reserved for LP (not for sale).
    uint256 public liquidityReserveObx;

    // ─── Phases ──────────────────────────────────────────────────────────────

    struct Phase {
        string  name;
        uint256 startTime;
        uint256 endTime;
        uint256 rateUsdt;    // OBX per 1 USDT × 10^18  (100 OBX/USDT → 100e18)
        uint256 tokenCap;    // max OBX to sell (18 dec)
        uint256 tokensSold;  // running total (18 dec)
        uint256 bonusBps;    // bonus in basis points
        uint256 dbPhaseId;   // Laravel ico_phases.id
        bool    active;
    }

    Phase[] public phases;

    // ─── Per-buyer tracking ───────────────────────────────────────────────────

    mapping(address => uint256) public totalUsdtSpent;
    mapping(address => uint256) public totalObxAllocated;
    mapping(uint256 => mapping(address => uint256)) public phaseObxAllocated;

    // ─── Events ──────────────────────────────────────────────────────────────

    event PhaseAdded(
        uint256 indexed contractPhaseIndex,
        uint256 indexed dbPhaseId,
        string  name,
        uint256 rateUsdt,
        uint256 tokenCap,
        uint256 startTime,
        uint256 endTime
    );

    event PhaseUpdated(
        uint256 indexed contractPhaseIndex,
        uint256 indexed dbPhaseId,
        uint256 rateUsdt,
        uint256 tokenCap,
        uint256 startTime,
        uint256 endTime,
        bool    active
    );

    /**
     * @dev All fields in this event are indexed or in data so explorers show full detail.
     *      topics[1]=buyer, topics[2]=contractPhaseIndex, topics[3]=dbPhaseId
     *      data = abi.encode(usdtAmount, obxAllocated, bonusObx, timestamp)
     */
    event TokensPurchased(
        address indexed buyer,
        uint256 indexed contractPhaseIndex,
        uint256 indexed dbPhaseId,
        uint256 usdtAmount,
        uint256 obxAllocated,
        uint256 bonusObx,
        uint256 timestamp
    );

    event LiquidityAdded(
        uint256 obxAmount,
        uint256 usdtAmount,
        uint256 lpTokens,
        address indexed pair
    );

    event LiquidityAddFailed(uint256 reserveUsdt, uint256 reserveObx);
    event LiquidityBpsChanged(uint256 oldBps, uint256 newBps);
    event LiquidityThresholdChanged(uint256 oldThreshold, uint256 newThreshold);
    event RouterChanged(address indexed oldRouter, address indexed newRouter);
    event TreasuryChanged(address indexed old, address indexed next);
    event UsdtAddressChanged(address indexed old, address indexed next);
    event ObxTokenChanged(address indexed old, address indexed next);
    event Paused(bool paused);
    event OwnershipTransferInitiated(address indexed current, address indexed pending);
    event OwnershipTransferred(address indexed previous, address indexed next);
    event UnsoldObxWithdrawn(address indexed to, uint256 amount);

    // ─── Modifiers ───────────────────────────────────────────────────────────

    modifier onlyOwner() {
        require(msg.sender == owner, "OBXPresale: not owner");
        _;
    }

    modifier notPaused() {
        require(!paused, "OBXPresale: paused");
        _;
    }

    modifier nonReentrant() {
        require(!_locked, "OBXPresale: reentrant call");
        _locked = true;
        _;
        _locked = false;
    }

    // ─── Constructor ─────────────────────────────────────────────────────────

    /**
     * @param _obxToken  OBXToken contract (fund this contract with OBX after deployment)
     * @param _usdt      USDT contract on this chain
     * @param _treasury  Wallet that receives USDT payments and LP tokens
     */
    constructor(address _obxToken, address _usdt, address _treasury) {
        require(_obxToken  != address(0), "Zero OBX address");
        require(_usdt      != address(0), "Zero USDT address");
        require(_treasury  != address(0), "Zero treasury address");
        owner    = msg.sender;
        obxToken = IERC20(_obxToken);
        usdt     = IERC20(_usdt);
        treasury = _treasury;
    }

    // ─── Admin: Phase Management ─────────────────────────────────────────────

    function addPhase(
        string  calldata name,
        uint256 startTime,
        uint256 endTime,
        uint256 rateUsdt,
        uint256 tokenCap,
        uint256 bonusBps,
        uint256 dbPhaseId
    ) external onlyOwner {
        require(endTime > startTime,       "End before start");
        require(rateUsdt > 0,              "Rate must be > 0");
        require(tokenCap > 0,              "Cap must be > 0");
        require(bonusBps <= MAX_BONUS_BPS, "Bonus exceeds 50 %");
        require(dbPhaseId > 0,             "dbPhaseId must be > 0");
        require(
            obxToken.balanceOf(address(this)) >= tokenCap + liquidityReserveObx,
            "Insufficient OBX reserve for phase cap"
        );

        phases.push(Phase({
            name:       name,
            startTime:  startTime,
            endTime:    endTime,
            rateUsdt:   rateUsdt,
            tokenCap:   tokenCap,
            tokensSold: 0,
            bonusBps:   bonusBps,
            dbPhaseId:  dbPhaseId,
            active:     true
        }));

        uint256 idx = phases.length - 1;
        emit PhaseAdded(idx, dbPhaseId, name, rateUsdt, tokenCap, startTime, endTime);
    }

    function updatePhase(
        uint256 contractPhaseIndex,
        string  calldata name,
        uint256 startTime,
        uint256 endTime,
        uint256 rateUsdt,
        uint256 tokenCap,
        uint256 bonusBps,
        bool    active
    ) external onlyOwner {
        require(contractPhaseIndex < phases.length, "Invalid phase index");
        require(endTime > startTime,                "End before start");
        require(rateUsdt > 0,                       "Rate must be > 0");
        require(bonusBps <= MAX_BONUS_BPS,          "Bonus exceeds 50 %");

        Phase storage p = phases[contractPhaseIndex];
        require(tokenCap >= p.tokensSold, "Cap below already sold");

        p.name      = name;
        p.startTime = startTime;
        p.endTime   = endTime;
        p.rateUsdt  = rateUsdt;
        p.tokenCap  = tokenCap;
        p.bonusBps  = bonusBps;
        p.active    = active;

        emit PhaseUpdated(
            contractPhaseIndex, p.dbPhaseId,
            rateUsdt, tokenCap, startTime, endTime, active
        );
    }

    function setPhaseActive(uint256 contractPhaseIndex, bool active) external onlyOwner {
        require(contractPhaseIndex < phases.length, "Invalid phase index");
        Phase storage p = phases[contractPhaseIndex];
        p.active = active;
        emit PhaseUpdated(
            contractPhaseIndex, p.dbPhaseId,
            p.rateUsdt, p.tokenCap, p.startTime, p.endTime, active
        );
    }

    // ─── Buy Tokens ──────────────────────────────────────────────────────────

    /**
     * @notice Purchase OBX with USDT.
     *
     * Pre-conditions:
     *   1. Register your wallet address in the Laravel dashboard
     *      (links on-chain address to your account for dashboard balance tracking)
     *   2. USDT.approve(presaleContract, usdtAmount)  ← must be done first
     *
     * What happens (all visible on BSCScan):
     *   • USDT Transfer: buyer → treasury  (the treasury portion)
     *   • USDT Transfer: buyer → this contract  (the LP reserve portion, if LP enabled)
     *   • OBX  Transfer: this contract → buyer  (tokens credited immediately)
     *   • TokensPurchased event
     *   • LiquidityAdded event (if LP threshold crossed)
     *
     * @param contractPhaseIndex  Index in the phases[] array
     * @param usdtAmount          USDT to spend (6 decimals).  Min: MIN_USDT (10 USDT).
     */
    function buyTokens(uint256 contractPhaseIndex, uint256 usdtAmount)
        external
        notPaused
        nonReentrant
    {
        // ── CHECKS ────────────────────────────────────────────────────────────
        require(usdtAmount >= MIN_USDT, "Below minimum (10 USDT)");
        require(contractPhaseIndex < phases.length, "Invalid phase");

        Phase storage p = phases[contractPhaseIndex];
        require(p.active,                       "Phase not active");
        require(block.timestamp >= p.startTime, "Phase not started");
        require(block.timestamp <= p.endTime,   "Phase ended");

        // OBX for buyer: base + bonus
        uint256 baseObx  = (usdtAmount * p.rateUsdt) / 1e6;
        uint256 bonusObx = (baseObx * p.bonusBps) / 10_000;
        uint256 totalObx = baseObx + bonusObx;       // goes to buyer

        require(totalObx > 0,                                       "OBX amount too small");
        require(p.tokensSold + totalObx <= p.tokenCap,             "Phase cap exceeded");

        // OBX and USDT for LP accumulation (0 when router not set)
        uint256 liqUsdt = 0;
        uint256 liqObx  = 0;
        if (liquidityBps > 0 && address(router) != address(0)) {
            liqUsdt = (usdtAmount * liquidityBps) / 10_000;
            liqObx  = (liqUsdt   * p.rateUsdt)   / 1e6;
        }

        uint256 treasuryUsdt = usdtAmount - liqUsdt;

        require(
            obxToken.balanceOf(address(this)) >= totalObx + liqObx + liquidityReserveObx,
            "Insufficient OBX reserve"
        );

        // ── EFFECTS (before any external call — CEI pattern) ──────────────────
        p.tokensSold                                       += totalObx;
        totalUsdtSpent[msg.sender]                         += usdtAmount;
        totalObxAllocated[msg.sender]                      += totalObx;
        phaseObxAllocated[contractPhaseIndex][msg.sender]  += totalObx;

        if (liqUsdt > 0) {
            liquidityReserveUsdt += liqUsdt;
            liquidityReserveObx  += liqObx;
        }

        // ── INTERACTIONS ──────────────────────────────────────────────────────

        // 1. Treasury: USDT from buyer to treasury
        if (treasuryUsdt > 0) {
            require(
                usdt.transferFrom(msg.sender, treasury, treasuryUsdt),
                "USDT treasury transfer failed — approve first"
            );
        }

        // 2. LP reserve: USDT from buyer to this contract
        if (liqUsdt > 0) {
            require(
                usdt.transferFrom(msg.sender, address(this), liqUsdt),
                "USDT LP transfer failed — approve first"
            );
        }

        // 3. OBX: from this contract to buyer
        require(
            obxToken.transfer(msg.sender, totalObx),
            "OBX transfer to buyer failed"
        );

        // 4. Emit purchase event (buyer, phase, db_phase_id in topics for indexing)
        emit TokensPurchased(
            msg.sender,
            contractPhaseIndex,
            p.dbPhaseId,
            usdtAmount,
            totalObx,
            bonusObx,
            block.timestamp
        );

        // 5. Auto-add liquidity if threshold reached
        if (
            address(router) != address(0) &&
            liquidityReserveUsdt >= liquidityThreshold &&
            liquidityReserveObx > 0
        ) {
            _tryAddLiquidity();
        }
    }

    // ─── Liquidity Management ────────────────────────────────────────────────

    /**
     * @notice Manually flush accumulated USDT+OBX reserves into the DEX LP.
     *         Callable by anyone once threshold is reached, or by owner at any time.
     *         Protected against reentrancy.
     */
    function flushLiquidity() external nonReentrant {
        require(address(router) != address(0), "Router not set");
        require(
            msg.sender == owner || liquidityReserveUsdt >= liquidityThreshold,
            "Threshold not reached"
        );
        require(liquidityReserveUsdt > 0 && liquidityReserveObx > 0, "No reserves");
        _tryAddLiquidity();
    }

    /**
     * @dev Internal: approve router, call addLiquidity, reset approvals.
     *      CEI: reserves reset to 0 BEFORE the external router call.
     *      If the router call reverts, reserves are restored via try/catch.
     */
    function _tryAddLiquidity() internal {
        uint256 usdtAmt = liquidityReserveUsdt;
        uint256 obxAmt  = liquidityReserveObx;

        // EFFECTS: reset reserves before external calls
        liquidityReserveUsdt = 0;
        liquidityReserveObx  = 0;

        // Approve router to spend our USDT and OBX
        usdt.approve(address(router), usdtAmt);
        obxToken.approve(address(router), obxAmt);

        try router.addLiquidity(
            address(obxToken),
            address(usdt),
            obxAmt,
            usdtAmt,
            0,              // amountAMin = 0 (controlled env, owner sets router)
            0,              // amountBMin = 0
            treasury,       // LP tokens go to treasury
            block.timestamp + 300
        ) returns (uint256 usedObx, uint256 usedUsdt, uint256 lpTokens) {
            // Get the pair address for the event
            address pair = _getPairAddress();
            emit LiquidityAdded(usedObx, usedUsdt, lpTokens, pair);

            // If router returned any excess, re-accumulate
            if (obxAmt  > usedObx)  liquidityReserveObx  += obxAmt  - usedObx;
            if (usdtAmt > usedUsdt) liquidityReserveUsdt += usdtAmt - usedUsdt;
        } catch {
            // LP addition failed (e.g. pair not created yet) — restore reserves
            liquidityReserveUsdt = usdtAmt;
            liquidityReserveObx  = obxAmt;
            emit LiquidityAddFailed(usdtAmt, obxAmt);
        }

        // Always reset router approvals to 0 after the operation (security)
        usdt.approve(address(router), 0);
        obxToken.approve(address(router), 0);
    }

    function _getPairAddress() internal view returns (address) {
        // Attempt to get pair address from factory; return zero if unavailable
        // solhint-disable-next-line no-empty-blocks
        try IUniswapV2Factory(router.factory()).getPair(
            address(obxToken), address(usdt)
        ) returns (address pair) {
            return pair;
        } catch {
            return address(0);
        }
    }

    // ─── Views ───────────────────────────────────────────────────────────────

    function totalPhases() external view returns (uint256) {
        return phases.length;
    }

    function getPhase(uint256 index) external view returns (Phase memory) {
        require(index < phases.length, "Invalid index");
        return phases[index];
    }

    function remainingTokens(uint256 index) external view returns (uint256) {
        require(index < phases.length, "Invalid index");
        Phase memory p = phases[index];
        return p.tokenCap > p.tokensSold ? p.tokenCap - p.tokensSold : 0;
    }

    /// @notice OBX tokens currently held by this contract (reserve minus LP reserve)
    function obxReserve() external view returns (uint256) {
        uint256 balance = obxToken.balanceOf(address(this));
        return balance > liquidityReserveObx ? balance - liquidityReserveObx : 0;
    }

    /// @notice Returns current active phase index or -1 if none active
    function activePhaseIndex() external view returns (int256) {
        for (uint256 i = 0; i < phases.length; i++) {
            Phase memory p = phases[i];
            if (
                p.active &&
                block.timestamp >= p.startTime &&
                block.timestamp <= p.endTime   &&
                p.tokensSold < p.tokenCap
            ) {
                return int256(i);
            }
        }
        return -1;
    }

    /// @notice Preview: how many OBX a USDT amount buys in a given phase
    function previewPurchase(uint256 contractPhaseIndex, uint256 usdtAmount)
        external view
        returns (uint256 baseObx, uint256 bonusObx, uint256 totalObx)
    {
        require(contractPhaseIndex < phases.length, "Invalid phase");
        Phase memory p = phases[contractPhaseIndex];
        baseObx  = (usdtAmount * p.rateUsdt) / 1e6;
        bonusObx = (baseObx * p.bonusBps) / 10_000;
        totalObx = baseObx + bonusObx;
    }

    // ─── Admin: Config ───────────────────────────────────────────────────────

    function setTreasury(address _treasury) external onlyOwner {
        require(_treasury != address(0), "Zero address");
        emit TreasuryChanged(treasury, _treasury);
        treasury = _treasury;
    }

    function setUsdtAddress(address _usdt) external onlyOwner {
        require(_usdt != address(0), "Zero address");
        emit UsdtAddressChanged(address(usdt), _usdt);
        usdt = IERC20(_usdt);
    }

    function setObxTokenAddress(address _obxToken) external onlyOwner {
        require(_obxToken != address(0), "Zero address");
        emit ObxTokenChanged(address(obxToken), _obxToken);
        obxToken = IERC20(_obxToken);
    }

    function setPaused(bool _paused) external onlyOwner {
        paused = _paused;
        emit Paused(_paused);
    }

    /// @notice Set or change the DEX router address (zero = disable auto-LP)
    function setRouter(address _router) external onlyOwner {
        emit RouterChanged(address(router), _router);
        router = IUniswapV2Router02(_router);
    }

    /// @notice Set the LP fee percentage (basis points). Max 10 % (1000 bps).
    function setLiquidityBps(uint256 _bps) external onlyOwner {
        require(_bps <= MAX_LIQ_BPS, "Exceeds max 10 %");
        emit LiquidityBpsChanged(liquidityBps, _bps);
        liquidityBps = _bps;
    }

    /// @notice Set the minimum accumulated USDT before auto-LP triggers
    function setLiquidityThreshold(uint256 _threshold) external onlyOwner {
        emit LiquidityThresholdChanged(liquidityThreshold, _threshold);
        liquidityThreshold = _threshold;
    }

    function transferOwnership(address newOwner) external onlyOwner {
        require(newOwner != address(0), "Zero address");
        pendingOwner = newOwner;
        emit OwnershipTransferInitiated(owner, newOwner);
    }

    function acceptOwnership() external {
        require(msg.sender == pendingOwner, "OBXPresale: not pending owner");
        emit OwnershipTransferred(owner, pendingOwner);
        owner        = pendingOwner;
        pendingOwner = address(0);
    }

    // ─── Emergency / Recovery ────────────────────────────────────────────────

    function withdrawUnsoldObx(address to, uint256 amount) external onlyOwner {
        require(to != address(0), "Zero address");
        uint256 available = obxToken.balanceOf(address(this));
        // Cannot withdraw LP reserve OBX — that is earmarked
        require(amount + liquidityReserveObx <= available, "Insufficient free OBX");
        require(obxToken.transfer(to, amount), "OBX withdraw failed");
        emit UnsoldObxWithdrawn(to, amount);
    }

    /// @notice Rescue any ERC-20 accidentally sent here (not OBX, not USDT LP reserve)
    function recoverToken(address token, uint256 amount) external onlyOwner {
        require(token != address(obxToken), "Use withdrawUnsoldObx for OBX");
        require(
            token != address(usdt) || amount <= usdt.balanceOf(address(this)) - liquidityReserveUsdt,
            "Cannot recover LP USDT reserve"
        );
        IERC20(token).transfer(owner, amount);
    }
}

// ─── Separate factory interface (used by _getPairAddress) ────────────────────
interface IUniswapV2Factory {
    function getPair(address tokenA, address tokenB) external view returns (address pair);
}
