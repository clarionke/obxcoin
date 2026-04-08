// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * @title OBXPresale
 * @notice Multi-chain BEP-20 presale contract for OBXCoin.
 *         Payment is accepted in USDT only (any chain deployment = same ABI).
 *         Phases are created/updated by the owner (mirrors the Laravel admin dashboard).
 *         Every operation is recorded on-chain and visible on BscScan.
 *
 * Integration flow:
 *  1. Admin creates phase in Laravel → PhaseController calls contract.addPhase() via web3
 *  2. User sends USDT → calls contract.buyTokens(phaseId, usdtAmount)
 *  3. Contract emits TokensPurchased event
 *  4. Laravel webhook listener catches event → credits OBXCoin balance in DB
 */

interface IERC20 {
    function totalSupply() external view returns (uint256);
    function balanceOf(address account) external view returns (uint256);
    function transfer(address to, uint256 amount) external returns (bool);
    function allowance(address owner, address spender) external view returns (uint256);
    function transferFrom(address from, address to, uint256 amount) external returns (bool);
}

contract OBXPresale {

    // ─── State ───────────────────────────────────────────────────────────────

    address public owner;
    address public treasury;      // receives USDT payments
    IERC20  public usdt;          // USDT token on the deployed chain
    bool    public paused;

    // Mirrors ico_phases table
    struct Phase {
        string  name;
        uint256 startTime;        // unix timestamp
        uint256 endTime;          // unix timestamp
        uint256 rateUsdt;         // OBX per 1 USDT, scaled by 1e18 (e.g. 100e18 = 100 OBX per USDT)
        uint256 tokenCap;         // max OBX tokens for this phase (18 decimals)
        uint256 tokensSold;       // running total OBX sold in this phase
        uint256 bonusBps;         // bonus in basis points (100 = 1%)
        uint256 dbPhaseId;        // Laravel ico_phases.id — for webhook matching
        bool    active;
    }

    Phase[] public phases;

    // per-buyer tracking (all phases combined)
    mapping(address => uint256) public totalUsdtSpent;
    mapping(address => uint256) public totalObxAllocated;

    // per-phase per-buyer tracking
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

    event TokensPurchased(
        address indexed buyer,
        uint256 indexed contractPhaseIndex,
        uint256 indexed dbPhaseId,
        uint256 usdtAmount,       // USDT paid (6 decimals)
        uint256 obxAllocated,     // OBX allocated including bonus (18 decimals)
        uint256 bonusObx,         // bonus portion
        uint256 timestamp
    );

    event TreasuryChanged(address indexed oldTreasury, address indexed newTreasury);
    event UsdtAddressChanged(address indexed oldUsdt, address indexed newUsdt);
    event Paused(bool paused);

    // ─── Modifiers ───────────────────────────────────────────────────────────

    modifier onlyOwner() {
        require(msg.sender == owner, "OBXPresale: not owner");
        _;
    }

    modifier notPaused() {
        require(!paused, "OBXPresale: paused");
        _;
    }

    // ─── Constructor ─────────────────────────────────────────────────────────

    /**
     * @param _usdt     USDT contract address on this chain
     * @param _treasury Wallet that receives USDT payments
     */
    constructor(address _usdt, address _treasury) {
        require(_usdt     != address(0), "Zero usdt address");
        require(_treasury != address(0), "Zero treasury address");
        owner    = msg.sender;
        usdt     = IERC20(_usdt);
        treasury = _treasury;
    }

    // ─── Admin: Phase Management ─────────────────────────────────────────────

    /**
     * @notice Add a new presale phase. Called by admin from Laravel dashboard.
     * @param name        Human-readable name (e.g. "Seed Round")
     * @param startTime   Unix timestamp
     * @param endTime     Unix timestamp
     * @param rateUsdt    OBX per 1 USDT * 1e18  (e.g. 100 OBX/USDT → 100e18)
     * @param tokenCap    Max OBX to sell in this phase (18 decimals)
     * @param bonusBps    Bonus tokens in basis points (100 = 1%)
     * @param dbPhaseId   Corresponding ico_phases.id from Laravel DB
     */
    function addPhase(
        string  calldata name,
        uint256 startTime,
        uint256 endTime,
        uint256 rateUsdt,
        uint256 tokenCap,
        uint256 bonusBps,
        uint256 dbPhaseId
    ) external onlyOwner {
        require(endTime > startTime,  "End must be after start");
        require(rateUsdt > 0,         "Rate must be > 0");
        require(tokenCap > 0,         "Cap must be > 0");

        phases.push(Phase({
            name:        name,
            startTime:   startTime,
            endTime:     endTime,
            rateUsdt:    rateUsdt,
            tokenCap:    tokenCap,
            tokensSold:  0,
            bonusBps:    bonusBps,
            dbPhaseId:   dbPhaseId,
            active:      true
        }));

        emit PhaseAdded(
            phases.length - 1,
            dbPhaseId,
            name,
            rateUsdt,
            tokenCap,
            startTime,
            endTime
        );
    }

    /**
     * @notice Update an existing phase. Called when admin edits phase in Laravel.
     */
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
        Phase storage p = phases[contractPhaseIndex];

        p.name      = name;
        p.startTime = startTime;
        p.endTime   = endTime;
        p.rateUsdt  = rateUsdt;
        p.tokenCap  = tokenCap;
        p.bonusBps  = bonusBps;
        p.active    = active;

        emit PhaseUpdated(
            contractPhaseIndex,
            p.dbPhaseId,
            rateUsdt,
            tokenCap,
            startTime,
            endTime,
            active
        );
    }

    /**
     * @notice Toggle a phase active/inactive without editing other fields.
     */
    function setPhaseActive(uint256 contractPhaseIndex, bool active) external onlyOwner {
        require(contractPhaseIndex < phases.length, "Invalid phase index");
        phases[contractPhaseIndex].active = active;

        emit PhaseUpdated(
            contractPhaseIndex,
            phases[contractPhaseIndex].dbPhaseId,
            phases[contractPhaseIndex].rateUsdt,
            phases[contractPhaseIndex].tokenCap,
            phases[contractPhaseIndex].startTime,
            phases[contractPhaseIndex].endTime,
            active
        );
    }

    // ─── Buy Tokens ──────────────────────────────────────────────────────────

    /**
     * @notice Purchase OBX tokens with USDT.
     *         User must approve this contract to spend usdtAmount before calling.
     * @param contractPhaseIndex  Phase index in the phases[] array
     * @param usdtAmount          Amount of USDT to pay (6 decimals, same as USDT standard)
     */
    function buyTokens(uint256 contractPhaseIndex, uint256 usdtAmount)
        external
        notPaused
    {
        require(usdtAmount > 0, "Amount must be > 0");
        require(contractPhaseIndex < phases.length, "Invalid phase");

        Phase storage p = phases[contractPhaseIndex];
        require(p.active,                           "Phase not active");
        require(block.timestamp >= p.startTime,     "Phase not started");
        require(block.timestamp <= p.endTime,       "Phase ended");

        // Calculate OBX: usdtAmount (6 dec) * rateUsdt (18 dec) / 1e6 = OBX (18 dec)
        uint256 baseObx   = (usdtAmount * p.rateUsdt) / 1e6;
        uint256 bonusObx  = (baseObx * p.bonusBps) / 10000;
        uint256 totalObx  = baseObx + bonusObx;

        require(p.tokensSold + totalObx <= p.tokenCap, "Phase cap exceeded");

        // Pull USDT from buyer directly to treasury
        require(
            usdt.transferFrom(msg.sender, treasury, usdtAmount),
            "USDT transfer failed"
        );

        // Update state
        p.tokensSold += totalObx;
        totalUsdtSpent[msg.sender]          += usdtAmount;
        totalObxAllocated[msg.sender]       += totalObx;
        phaseObxAllocated[contractPhaseIndex][msg.sender] += totalObx;

        emit TokensPurchased(
            msg.sender,
            contractPhaseIndex,
            p.dbPhaseId,
            usdtAmount,
            totalObx,
            bonusObx,
            block.timestamp
        );
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

    /**
     * @notice Returns the index of the currently running phase, or -1 if none.
     */
    function activePhaseIndex() external view returns (int256) {
        for (uint256 i = 0; i < phases.length; i++) {
            Phase memory p = phases[i];
            if (
                p.active &&
                block.timestamp >= p.startTime &&
                block.timestamp <= p.endTime &&
                p.tokensSold < p.tokenCap
            ) {
                return int256(i);
            }
        }
        return -1;
    }

    /**
     * @notice Preview how much OBX a given USDT amount fetches in a phase.
     */
    function previewPurchase(uint256 contractPhaseIndex, uint256 usdtAmount)
        external view returns (uint256 baseObx, uint256 bonusObx, uint256 totalObx)
    {
        require(contractPhaseIndex < phases.length, "Invalid phase");
        Phase memory p = phases[contractPhaseIndex];
        baseObx  = (usdtAmount * p.rateUsdt) / 1e6;
        bonusObx = (baseObx * p.bonusBps) / 10000;
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

    function setPaused(bool _paused) external onlyOwner {
        paused = _paused;
        emit Paused(_paused);
    }

    function transferOwnership(address newOwner) external onlyOwner {
        require(newOwner != address(0), "Zero address");
        owner = newOwner;
    }

    // ─── Emergency ───────────────────────────────────────────────────────────

    /**
     * @notice Recover any ERC20 accidentally sent to this contract.
     *         This contract never holds USDT (goes directly to treasury).
     */
    function recoverToken(address token, uint256 amount) external onlyOwner {
        IERC20(token).transfer(owner, amount);
    }
}
