// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * @title  OBXToken  v3
 * @notice Deflationary BEP-20 / ERC-20 token for OBXCoin.
 *
 * ─── Tokenomics ───────────────────────────────────────────────────────────
 *  • 0.05 % BURN on every non-exempt transfer → permanently decreasing supply.
 *    Implemented as Transfer(from, address(0), burnAmount) which appears on
 *    BSCScan/any explorer as an official token burn event.
 *  • Fee-exempt list: owner, presale contract, DEX router, LP pair(s).
 *
 * ─── Deployment checklist ─────────────────────────────────────────────────
 *  1.  Deploy OBXToken(_initialSupply)
 *  2.  Deploy OBXPresale(obxToken, usdt, treasury)
 *  3.  OBXToken.setFeeExempt(presaleContract, true)   ← BEFORE transferring OBX to presale
 *  4.  OBXToken.setFeeExempt(routerAddress,   true)   ← PancakeSwap / Uniswap V2 router
 *  5.  OBXToken.setFeeExempt(lpPairAddress,   true)   ← after creating the OBX/USDT pair
 *  6.  OBXToken.transfer(presaleContract, presaleAllocation)
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

    /// @dev Fee-exempt addresses (presale, router, LP pair, owner).
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

    // ─── Internal ────────────────────────────────────────────────────────────

    function _transfer(address from, address to, uint256 amount) internal {
        require(from != address(0), "OBXToken: from zero");
        require(to   != address(0), "OBXToken: to zero");
        require(balanceOf[from] >= amount, "OBXToken: insufficient balance");

        // 0.05 % burn — skipped when either address is fee-exempt
        if (!feeExempt[from] && !feeExempt[to] && amount > 0) {
            uint256 burnAmount = (amount * BURN_FEE_BPS) / 10_000;
            if (burnAmount > 0) {
                balanceOf[from] -= burnAmount;
                totalSupply     -= burnAmount;
                emit Transfer(from, address(0), burnAmount); // standard burn
                emit Burn(from, burnAmount, totalSupply);
                amount -= burnAmount;
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
