// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * @title  OBXToken  v3
 * @notice Deflationary BEP-20 / ERC-20 token for OBXCoin (multichain).
 *
 * ✓ Compatible with: BSC (BEP-20), Ethereum (ERC-20), Polygon, Arbitrum, Optimism
 * ✓ Fully ERC-20 compatible with 18 decimals
 * ✓ Deflationary: 0.05% burn per transfer (after BURN_FLOOR: 0% fee forever)
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
 *  • Transfer burn scope: applies to all transfers until burn floor is reached
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

    /// @dev Retained for backward compatibility with existing admin tooling.
    ///      Transfer burn no longer checks fee exemptions.
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
        feeExempt[msg.sender] = true;
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

    /// @notice Explicit burn used by staking contracts and power users.
    /// @dev Burn is capped by BURN_FLOOR; may burn less than requested near floor.
    function burn(uint256 amount) external notPaused returns (bool) {
        require(balanceOf[msg.sender] >= amount, "OBXToken: insufficient balance");
        _burn(msg.sender, amount);
        return true;
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    function _transfer(address from, address to, uint256 amount) internal {
        require(from != address(0), "OBXToken: from zero");
        require(to   != address(0), "OBXToken: to zero");
        require(balanceOf[from] >= amount, "OBXToken: insufficient balance");

        // 0.05 % burn on all transfers until burn floor is reached.
        if (!burnComplete && amount > 0) {
            uint256 burnAmount = (amount * BURN_FEE_BPS) / 10_000;
            if (burnAmount > 0) {
                uint256 burned = _burn(from, burnAmount);
                amount -= burned;
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

    function _burn(address from, uint256 requested) internal returns (uint256 burnedAmount) {
        if (requested == 0 || burnComplete) {
            return 0;
        }

        uint256 available = totalSupply > BURN_FLOOR
            ? totalSupply - BURN_FLOOR
            : 0;

        burnedAmount = requested > available ? available : requested;

        if (burnedAmount > 0) {
            balanceOf[from] -= burnedAmount;
            totalSupply     -= burnedAmount;
            emit Transfer(from, address(0), burnedAmount);
            emit Burn(from, burnedAmount, totalSupply);
        }

        if (totalSupply <= BURN_FLOOR && !burnComplete) {
            burnComplete = true;
            emit BurnComplete(totalSupply);
        }
    }

    // ─── Admin ───────────────────────────────────────────────────────────────

    /// @notice Set or clear fee-exemption for presale, router, LP pair, etc.
    function setFeeExempt(address account, bool exempt) external onlyOwner {
        require(account != address(0), "OBXToken: zero address");
        feeExempt[account] = exempt;
        emit FeeExemptUpdated(account, exempt);
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
        feeExempt[pendingOwner] = true;
        feeExempt[owner]        = false;
        emit OwnershipTransferred(owner, pendingOwner);
        owner        = pendingOwner;
        pendingOwner = address(0);
    }
}
