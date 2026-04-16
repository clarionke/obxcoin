// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * @title  OBXAirdrop  v1
 * @notice Distributes 5% of OBXCoin initial supply (5,000,000 OBX) via daily
 *         claimable airdrops with a locked-until-paid unlock mechanism.
 *
 * ─── Key Rules ──────────────────────────────────────────────────────────────
 *  • Admin funds this contract with 5,000,000 OBX (5% of 100M supply)
 *  • Admin creates a campaign: start_time, end_time, daily_claim_amount
 *  • Users can call claim() ONCE per calendar day during the campaign window
 *  • Claimed tokens are LOCKED inside the contract (non-transferable) until:
 *      a) The campaign's end_time has passed, AND
 *      b) The admin has called revealUnlockFee() to disclose the fee, AND
 *      c) The user calls unlock() and pays the USDT fee
 *  • The unlock fee amount is intentionally HIDDEN during the campaign and only
 *    revealed by the admin after campaign ends — creating anticipation
 *  • Once a user calls unlock() and pays the fee, their OBX is transferred to
 *    their own wallet instantly
 *
 * ─── Tokenomics ─────────────────────────────────────────────────────────────
 *  • Airdrop Allocation:  5,000,000 OBX (5% of 100,000,000 initial supply)
 *  • Presale Allocation: 20,000,000 OBX (20%)
 *  • Remaining to deployer: 75,000,000 OBX (75%) for liquidity and team
 *
 * ─── Multichain ──────────────────────────────────────────────────────────────
 *  Deploy one OBXAirdrop per chain. Each instance holds its own OBX and USDT
 *  references. Laravel backend tracks which chain each user is on.
 *
 * ─── Deployment Steps ────────────────────────────────────────────────────────
 *  1. Deploy OBXToken
 *  2. Deploy OBXAirdrop(obxToken, usdt)
 *  3. OBXToken.setFeeExempt(airdropContract, true)     ← no burn on airdrop transfers
 *  4. OBXToken.transfer(airdropContract, 5_000_000)    ← 5% airdrop allocation
 *  5. OBXAirdrop.createCampaign(...)                   ← admin creates first campaign
 *
 * ─── Security ────────────────────────────────────────────────────────────────
 *  ✓ Reentrancy guard on claim() and unlock()
 *  ✓ CEI pattern throughout
 *  ✓ Per-day claim tracked by day index to prevent duplicate claims
 *  ✓ Max campaign duration: 365 days (prevents unbounded loop risk)
 *  ✓ unlock() checks: campaign ended + fee revealed + msg.sender owns balance
 *  ✓ Two-step ownership transfer
 *  ✓ Emergency pause
 */

interface IERC20 {
    function balanceOf(address account)                            external view returns (uint256);
    function transfer(address to, uint256 amount)                  external returns (bool);
    function transferFrom(address from, address to, uint256 amount) external returns (bool);
    function allowance(address owner, address spender)             external view returns (uint256);
}

contract OBXAirdrop {

    // ─── Constants ───────────────────────────────────────────────────────────

    /// @dev Maximum campaign length to prevent gas-bomb edge cases
    uint256 public constant MAX_CAMPAIGN_DAYS = 365;

    // ─── Ownership ───────────────────────────────────────────────────────────

    address public owner;
    address public pendingOwner;

    // ─── Tokens ──────────────────────────────────────────────────────────────

    IERC20 public obxToken;
    IERC20 public usdt;

    // ─── Campaign ────────────────────────────────────────────────────────────

    struct Campaign {
        uint256 startTime;          // unix timestamp — campaign opens
        uint256 endTime;            // unix timestamp — campaign closes
        uint256 dailyClaimAmount;   // OBX (18 dec) per user per day
        uint256 unlockFeeUsdt;      // USDT (6 dec) to unlock airdrop tokens
        bool    feeRevealed;        // true after admin calls revealUnlockFee()
        bool    active;
    }

    Campaign public campaign;
    bool     public campaignCreated;

    // ─── Per-user state ──────────────────────────────────────────────────────

    /// @dev Total OBX locked for this user (credited by daily claims, lives in contract)
    mapping(address => uint256) public lockedBalance;

    /// @dev day-index => user => claimed — prevents double-claim on same UTC day
    ///      dayIndex = block.timestamp / 86400
    mapping(uint256 => mapping(address => bool)) public dailyClaimed;

    /// @dev True once user paid the unlock fee and their OBX was transferred out
    mapping(address => bool) public unlocked;

    /// @dev Total USDT collected from unlock fees (admin can withdraw)
    uint256 public totalUnlockFeesCollected;

    /// @dev Tracks OBX credited to users via claim() but not yet unlocked.
    ///      Protected from reclaimUnspentObx() so users' pending OBX is safe.
    uint256 public totalLockedObx;

    // ─── Reentrancy ──────────────────────────────────────────────────────────

    bool private _locked;

    // ─── Pause ───────────────────────────────────────────────────────────────

    bool public paused;

    // ─── Events ──────────────────────────────────────────────────────────────

    event CampaignCreated(
        uint256 startTime,
        uint256 endTime,
        uint256 dailyClaimAmount
    );

    event CampaignUpdated(
        uint256 startTime,
        uint256 endTime,
        uint256 dailyClaimAmount
    );

    event Claimed(
        address indexed user,
        uint256 dayIndex,
        uint256 amount
    );

    event UnlockFeeRevealed(uint256 feeUsdt);

    event Unlocked(
        address indexed user,
        uint256 obxAmount,
        uint256 feeUsdt
    );

    event UnlockFeesWithdrawn(address indexed to, uint256 amount);

    event UnspentObxReclaimed(address indexed to, uint256 amount);

    event Paused(bool paused);

    event OwnershipTransferInitiated(address indexed current, address indexed pending);
    event OwnershipTransferred(address indexed previous, address indexed next);

    // ─── Modifiers ───────────────────────────────────────────────────────────

    modifier onlyOwner() {
        require(msg.sender == owner, "OBXAirdrop: not owner");
        _;
    }

    modifier notPaused() {
        require(!paused, "OBXAirdrop: paused");
        _;
    }

    modifier nonReentrant() {
        require(!_locked, "OBXAirdrop: reentrant call");
        _locked = true;
        _;
        _locked = false;
    }

    // ─── Constructor ─────────────────────────────────────────────────────────

    constructor(address _obxToken, address _usdt) {
        require(_obxToken != address(0), "Zero OBX address");
        require(_usdt     != address(0), "Zero USDT address");
        owner    = msg.sender;
        obxToken = IERC20(_obxToken);
        usdt     = IERC20(_usdt);
    }

    // ─── Admin: Campaign Management ──────────────────────────────────────────

    /**
     * @notice Create the airdrop campaign. Can only be called once. Fund the
     *         contract with OBX before calling this.
     * @param startTime         Unix timestamp when daily claiming opens
     * @param endTime           Unix timestamp when claiming stops (must be at least 1 day after start)
     * @param dailyClaimAmount  OBX in wei (18 decimals) each user may claim per day
     */
    function createCampaign(
        uint256 startTime,
        uint256 endTime,
        uint256 dailyClaimAmount
    ) external onlyOwner {
        require(!campaignCreated,          "OBXAirdrop: campaign already created");
        require(startTime < endTime,       "OBXAirdrop: start must be before end");
        require(
            endTime - startTime <= MAX_CAMPAIGN_DAYS * 1 days,
            "OBXAirdrop: campaign too long"
        );
        require(dailyClaimAmount > 0,      "OBXAirdrop: daily amount must be > 0");
        require(
            obxToken.balanceOf(address(this)) >= dailyClaimAmount,
            "OBXAirdrop: fund contract with OBX first"
        );

        campaign = Campaign({
            startTime:       startTime,
            endTime:         endTime,
            dailyClaimAmount: dailyClaimAmount,
            unlockFeeUsdt:   0,
            feeRevealed:     false,
            active:          true
        });
        campaignCreated = true;

        emit CampaignCreated(startTime, endTime, dailyClaimAmount);
    }

    /**
     * @notice Update campaign timing / daily amount BEFORE it starts.
     *         Cannot update a campaign that has already started.
     */
    function updateCampaign(
        uint256 startTime,
        uint256 endTime,
        uint256 dailyClaimAmount
    ) external onlyOwner {
        require(campaignCreated,                       "OBXAirdrop: no campaign");
        require(block.timestamp < campaign.startTime, "OBXAirdrop: campaign already started");
        require(startTime < endTime,                   "OBXAirdrop: start must be before end");
        require(
            endTime - startTime <= MAX_CAMPAIGN_DAYS * 1 days,
            "OBXAirdrop: campaign too long"
        );
        require(dailyClaimAmount > 0, "OBXAirdrop: daily amount must be > 0");

        campaign.startTime       = startTime;
        campaign.endTime         = endTime;
        campaign.dailyClaimAmount = dailyClaimAmount;

        emit CampaignUpdated(startTime, endTime, dailyClaimAmount);
    }

    /**
     * @notice Reveal the unlock fee after the campaign ends.
     *         The fee was intentionally hidden from users during the campaign.
     *         Once revealed, users can call unlock() to pay and receive their OBX.
     * @param feeUsdt  Amount in USDT (6 decimals) users must pay to unlock their airdrop
     */
    function revealUnlockFee(uint256 feeUsdt) external onlyOwner {
        require(campaignCreated,                        "OBXAirdrop: no campaign");
        require(block.timestamp >= campaign.endTime,   "OBXAirdrop: campaign not ended yet");
        require(!campaign.feeRevealed,                 "OBXAirdrop: fee already revealed");
        require(feeUsdt > 0,                           "OBXAirdrop: fee must be > 0");

        campaign.unlockFeeUsdt = feeUsdt;
        campaign.feeRevealed   = true;

        emit UnlockFeeRevealed(feeUsdt);
    }

    // ─── User: Claim ─────────────────────────────────────────────────────────

    /**
     * @notice Claim today's airdrop allocation. One claim per UTC day per user.
     *         Tokens are credited to lockedBalance inside the contract — they are
     *         NOT transferred out until the user calls unlock() after the campaign.
     */
    function claim() external notPaused nonReentrant {
        require(campaignCreated,                         "OBXAirdrop: no active campaign");
        require(campaign.active,                         "OBXAirdrop: campaign inactive");
        require(block.timestamp >= campaign.startTime,  "OBXAirdrop: campaign not started");
        require(block.timestamp <  campaign.endTime,    "OBXAirdrop: campaign ended");
        require(!unlocked[msg.sender],                   "OBXAirdrop: already unlocked");

        uint256 dayIndex = block.timestamp / 1 days;
        require(!dailyClaimed[dayIndex][msg.sender],     "OBXAirdrop: already claimed today");

        uint256 amount = campaign.dailyClaimAmount;
        require(
            obxToken.balanceOf(address(this)) >= lockedBalance[msg.sender] + amount,
            "OBXAirdrop: contract OBX depleted"
        );

        // ── CEI: state first ──────────────────────────────────────────────────
        dailyClaimed[dayIndex][msg.sender] = true;
        lockedBalance[msg.sender]          += amount;
        totalLockedObx                    += amount;

        emit Claimed(msg.sender, dayIndex, amount);
    }

    // ─── User: Unlock ────────────────────────────────────────────────────────

    /**
     * @notice Pay the USDT unlock fee to receive your locked airdrop OBX.
     *
     * Requirements:
     *  • Campaign must have ended
     *  • Admin must have revealed the unlock fee
     *  • Caller must have a non-zero locked balance
     *  • Caller must have approved this contract to spend their USDT
     *
     * After payment, the caller's full locked OBX balance is transferred to their
     * wallet in the same transaction.
     */
    function unlock() external notPaused nonReentrant {
        require(campaignCreated,                        "OBXAirdrop: no campaign");
        require(block.timestamp >= campaign.endTime,   "OBXAirdrop: campaign not ended yet");
        require(campaign.feeRevealed,                  "OBXAirdrop: unlock fee not yet revealed");
        require(!unlocked[msg.sender],                 "OBXAirdrop: already unlocked");

        uint256 userBalance = lockedBalance[msg.sender];
        require(userBalance > 0,                       "OBXAirdrop: no airdrop balance");

        uint256 fee = campaign.unlockFeeUsdt;
        require(fee > 0,                               "OBXAirdrop: fee not set");
        require(
            usdt.allowance(msg.sender, address(this)) >= fee,
            "OBXAirdrop: approve USDT first"
        );

        // ── CEI: state before external calls ─────────────────────────────────
        unlocked[msg.sender]      = true;
        lockedBalance[msg.sender] = 0;
        totalUnlockFeesCollected  += fee;
        totalLockedObx           -= (totalLockedObx >= userBalance ? userBalance : totalLockedObx);

        // ── External: collect USDT fee ────────────────────────────────────────
        require(
            usdt.transferFrom(msg.sender, address(this), fee),
            "OBXAirdrop: USDT transfer failed"
        );

        // ── External: send OBX to user ────────────────────────────────────────
        require(
            obxToken.transfer(msg.sender, userBalance),
            "OBXAirdrop: OBX transfer failed"
        );

        emit Unlocked(msg.sender, userBalance, fee);
    }

    // ─── Admin: Withdrawals ─────────────────────────────────────────────────

    /**
     * @notice Withdraw accumulated USDT unlock fees to the owner wallet.
     */
    function withdrawUnlockFees(address to) external onlyOwner nonReentrant {
        require(to != address(0),                  "OBXAirdrop: zero address");
        uint256 balance = usdt.balanceOf(address(this));
        require(balance > 0,                       "OBXAirdrop: nothing to withdraw");
        uint256 amount  = balance;
        totalUnlockFeesCollected = 0;

        require(usdt.transfer(to, amount),         "OBXAirdrop: USDT transfer failed");
        emit UnlockFeesWithdrawn(to, amount);
    }

    /**
     * @notice Reclaim unspent OBX after campaign ends (unclaimed / abandoned tokens).
     *         Only callable after campaign end + a 30-day grace period so users
     *         still have time to unlock.
     * @param to  Address to receive unclaimed OBX
     */
    function reclaimUnspentObx(address to) external onlyOwner nonReentrant {
        require(to != address(0),                     "OBXAirdrop: zero address");
        require(campaignCreated,                      "OBXAirdrop: no campaign");
        require(
            block.timestamp >= campaign.endTime + 30 days,
            "OBXAirdrop: 30-day grace period not over"
        );

        uint256 balance = obxToken.balanceOf(address(this));
        require(balance > 0,                          "OBXAirdrop: no OBX to reclaim");

        // Only reclaim truly unclaimed OBX — never touch tokens locked for users
        require(balance > totalLockedObx,              "OBXAirdrop: all OBX is locked for users");
        uint256 reclaimable = balance - totalLockedObx;
        require(obxToken.transfer(to, reclaimable),    "OBXAirdrop: OBX transfer failed");
        emit UnspentObxReclaimed(to, reclaimable);
    }

    // ─── Views ───────────────────────────────────────────────────────────────

    /**
     * @notice Returns the current UTC day index.
     *         Used by the front-end to check if user has claimed today.
     */
    function currentDayIndex() external view returns (uint256) {
        return block.timestamp / 1 days;
    }

    /**
     * @notice Returns whether a specific user has claimed on a specific day.
     */
    function hasClaimedOnDay(address user, uint256 dayIndex) external view returns (bool) {
        return dailyClaimed[dayIndex][user];
    }

    /**
     * @notice Whether the unlock fee is visible. False during campaign; true
     *         once admin calls revealUnlockFee() after campaign ends.
     */
    function isUnlockFeeRevealed() external view returns (bool) {
        return campaign.feeRevealed;
    }

    /**
     * @notice Returns the unlock fee only once it has been revealed.
     *         Reverts if fee has not been revealed yet.
     */
    function getUnlockFee() external view returns (uint256) {
        require(campaign.feeRevealed, "OBXAirdrop: fee not yet revealed");
        return campaign.unlockFeeUsdt;
    }

    /**
     * @notice Full campaign snapshot for a front-end dashboard.
     */
    function getCampaignInfo() external view returns (
        uint256 startTime,
        uint256 endTime,
        uint256 dailyClaimAmount,
        bool    feeRevealed,
        bool    active,
        uint256 obxReserve
    ) {
        return (
            campaign.startTime,
            campaign.endTime,
            campaign.dailyClaimAmount,
            campaign.feeRevealed,
            campaign.active,
            obxToken.balanceOf(address(this))
        );
    }

    // ─── Admin: Pause ────────────────────────────────────────────────────────

    function setPaused(bool _paused) external onlyOwner {
        paused = _paused;
        emit Paused(_paused);
    }

    function setCampaignActive(bool _active) external onlyOwner {
        require(campaignCreated, "OBXAirdrop: no campaign");
        campaign.active = _active;
    }

    // ─── Ownership ───────────────────────────────────────────────────────────

    function transferOwnership(address newOwner) external onlyOwner {
        require(newOwner != address(0), "OBXAirdrop: zero address");
        pendingOwner = newOwner;
        emit OwnershipTransferInitiated(owner, newOwner);
    }

    function acceptOwnership() external {
        require(msg.sender == pendingOwner, "OBXAirdrop: not pending owner");
        emit OwnershipTransferred(owner, pendingOwner);
        owner        = pendingOwner;
        pendingOwner = address(0);
    }
}
