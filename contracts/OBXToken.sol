// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * @title  OBXToken  v3
 * @notice Deflationary BEP-20 / ERC-20 token for OBXCoin (multichain).
 *
 * ✓ Compatible with: BSC (BEP-20), Ethereum (ERC-20), Polygon, Arbitrum, Optimism
 * ✓ Fully ERC-20 compatible with 18 decimals
 * ✓ Deflationary: 0.05% burn per transfer for everyone (after BURN_FLOOR: 0% fee forever)
 * ✓ Programmed Scarcity: Burn stops at 41M OBX floor (59M total burn from 100M supply)
 *
 * ─── Tokenomics ───────────────────────────────────────────────────────────
 *  • Initial Supply:      100,000,000 OBX
 *  • Presale Allocation:   20,000,000 OBX (20%) → OBXPresale contract
 *  • Airdrop Allocation:    5,000,000 OBX ( 5%) → OBXAirdrop contract
 *  • Remaining to Deployer: 75,000,000 OBX (75%) for liquidity / team vesting
 *  • Burn Fee:            0.05% (5 / 10,000 BPS) on every transfer
 *  • Burn Floor:          41,000,000 OBX (41% of initial supply remains)
 *  • Max Burnable:        59,000,000 OBX (59% of initial supply)
 *  • After floor reached: Burns are 0%, all transfers are fee-free forever
 *  • Fee-exempt list:    Disabled (no address exemptions)
 *
 * ─── Deployment Checklist ─────────────────────────────────────────────────
 *  1. Deploy OBXToken(100_000_000)
 *  2. Deploy OBXPresale(obxToken, usdt, treasury)
 *  3. Deploy OBXAirdrop(obxToken, usdt)
 *  4. OBXToken.transfer(presaleAddress, 20_000_000)   ← 20% presale allocation
 *  5. OBXToken.transfer(airdropAddress,  5_000_000)   ←  5% airdrop allocation
 *  6. OBXPresale.setRouter(routerAddress)             ← Enable auto-liquidity
 *  7. OBXAirdrop.createCampaign(start, end, daily)    ← Admin creates first airdrop campaign
 *  8. Verify balances via OBXToken.balanceOf()
 *
 * ─── Security Notes ───────────────────────────────────────────────────────
 *  • Owner can be transferred (2-step transfer via transferOwnership/acceptOwnership)
 *  • Paused flag allows emergency freeze of all transfers
 *  • Burn calculation uses safe arithmetic (no overflow/underflow)
 *  • Burn is capped at BURN_FLOOR to prevent supply going negative
 */
contract OBXToken {

    // ─── ERC-20 metadata ─────────────────────────────────────────────────────
    string  public constant name     = "OBXCoin";
    string  public constant symbol   = "OBX";
    uint8   public constant decimals = 18;

    uint256 public totalSupply;

    // ─── Tokenomics ──────────────────────────────────────────────────────────
    /// @dev 0.05 % burn per transfer: 5 / 10_000 = 0.0005 = 0.05 %
    uint256 public constant BURN_FEE_BPS = 5;

    /// @dev Burn stops permanently once totalSupply reaches this floor.
    ///      100 M initial supply − 59 M burned = 41 M final supply.
    uint256 public constant BURN_FLOOR = 41_000_000 * (10 ** 18);

    /// @dev Set to true the first time totalSupply hits BURN_FLOOR.
    ///      Checked before every burn so the SLOAD is avoided when already true.
    bool public burnComplete;

    /// @dev Kept for backward ABI compatibility. Exemptions are disabled.
    mapping(address => bool) public feeExempt;

    // ─── Ownership ───────────────────────────────────────────────────────────
    address public owner;
    address public pendingOwner;

    // ─── ERC-20 storage ──────────────────────────────────────────────────────
    mapping(address => uint256)                     public balanceOf;
    mapping(address => mapping(address => uint256)) public allowance;

    // ─── Emergency flag ──────────────────────────────────────────────────────
    bool public paused;

    // ─── Events ──────────────────────────────────────────────────────────────
    event Transfer(address indexed from, address indexed to, uint256 value);
    event Approval(address indexed owner, address indexed spender, uint256 value);
    /// Extra burn event (alongside Transfer to address(0)) for indexers/dashboards
    event Burn(address indexed from, uint256 burnAmount, uint256 newTotalSupply);
    /// Emitted once when totalSupply first reaches BURN_FLOOR — burn is over forever
    event BurnComplete(uint256 finalSupply);
    event FeeExemptUpdated(address indexed account, bool exempt);
    event Paused(bool paused);
    event OwnershipTransferInitiated(address indexed current, address indexed pending);
    event OwnershipTransferred(address indexed previous, address indexed next);

    // ─── Modifiers ───────────────────────────────────────────────────────────
    modifier onlyOwner() {
        require(msg.sender == owner, "OBXToken: not owner");
        _;
    }
    modifier notPaused() {
        require(!paused, "OBXToken: transfers paused");
        _;
    }

    // ─── Constructor ─────────────────────────────────────────────────────────
    /// @param _initialSupply  Human units (e.g. 1_000_000_000 for 1 billion OBX).
    constructor(uint256 _initialSupply) {
        require(_initialSupply > 0, "OBXToken: zero supply");
        owner                 = msg.sender;
        totalSupply           = _initialSupply * (10 ** decimals);
        balanceOf[msg.sender] = totalSupply;
        emit Transfer(address(0), msg.sender, totalSupply);
    }

    // ─── ERC-20 ──────────────────────────────────────────────────────────────

    function transfer(address to, uint256 amount) external notPaused returns (bool) {
        _transfer(msg.sender, to, amount);
        return true;
    }

    function approve(address spender, uint256 amount) external returns (bool) {
        _approve(msg.sender, spender, amount);
        return true;
    }

    function transferFrom(address from, address to, uint256 amount) external notPaused returns (bool) {
        uint256 current = allowance[from][msg.sender];
        require(current >= amount, "OBXToken: insufficient allowance");
        if (current != type(uint256).max) {
            allowance[from][msg.sender] = current - amount;
            emit Approval(from, msg.sender, current - amount);
        }
        _transfer(from, to, amount);
        return true;
    }

    function increaseAllowance(address spender, uint256 added) external returns (bool) {
        _approve(msg.sender, spender, allowance[msg.sender][spender] + added);
        return true;
    }

    function decreaseAllowance(address spender, uint256 subtracted) external returns (bool) {
        uint256 cur = allowance[msg.sender][spender];
        require(cur >= subtracted, "OBXToken: allowance below zero");
        _approve(msg.sender, spender, cur - subtracted);
        return true;
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    function _transfer(address from, address to, uint256 amount) internal {
        require(from != address(0), "OBXToken: from zero");
        require(to   != address(0), "OBXToken: to zero");
        require(balanceOf[from] >= amount, "OBXToken: insufficient balance");

        // 0.05 % burn for every transfer (no exemptions), until burn floor is reached.
        if (!burnComplete && amount > 0) {
            uint256 burnAmount = (amount * BURN_FEE_BPS) / 10_000;
            if (burnAmount > 0) {
                // ── Programmed Scarcity: cap burn at the floor ────────────────
                // If this burn would push totalSupply below 41 M, only burn
                // the exact remainder so we land precisely on the floor.
                uint256 available = totalSupply > BURN_FLOOR
                    ? totalSupply - BURN_FLOOR
                    : 0;
                if (burnAmount > available) {
                    burnAmount = available;
                }
                if (burnAmount > 0) {
                    balanceOf[from] -= burnAmount;
                    totalSupply     -= burnAmount;
                    emit Transfer(from, address(0), burnAmount); // standard burn
                    emit Burn(from, burnAmount, totalSupply);
                    amount -= burnAmount;
                }
                // ── Lock burn permanently once floor is reached ───────────────
                if (totalSupply <= BURN_FLOOR && !burnComplete) {
                    burnComplete = true;
                    emit BurnComplete(totalSupply);
                }
            }
        }

        balanceOf[from] -= amount;
        balanceOf[to]   += amount;
        emit Transfer(from, to, amount);
    }

    function _approve(address _owner, address spender, uint256 amount) internal {
        require(_owner  != address(0), "OBXToken: approve from zero");
        require(spender != address(0), "OBXToken: approve to zero");
        allowance[_owner][spender] = amount;
        emit Approval(_owner, spender, amount);
    }

    // ─── Admin ───────────────────────────────────────────────────────────────

    /// @notice Disabled: no address can be exempt from burn.
    function setFeeExempt(address account, bool exempt) external onlyOwner {
        account;
        exempt;
        revert("OBXToken: fee exemptions disabled");
    }

    function setPaused(bool _paused) external onlyOwner {
        paused = _paused;
        emit Paused(_paused);
    }

    function transferOwnership(address newOwner) external onlyOwner {
        require(newOwner != address(0), "OBXToken: zero address");
        pendingOwner = newOwner;
        emit OwnershipTransferInitiated(owner, newOwner);
    }

    function acceptOwnership() external {
        require(msg.sender == pendingOwner, "OBXToken: not pending owner");
        emit OwnershipTransferred(owner, pendingOwner);
        owner        = pendingOwner;
        pendingOwner = address(0);
    }
}
