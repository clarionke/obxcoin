// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * OBXStaking – Full on-chain staking for OBX token.
 *
 * Key features
 * ─────────────
 * • Configurable burn on stake AND on unstake (basis points, max 10 %)
 * • Multiple pools (tiers), each with its own duration and APY
 * • All token burns call obxToken.burn() → visible on BSCScan as real burns
 * • Events emitted for every action so the explorer shows full audit trail
 * • Reward reserve funded by admin; accrues pro-rata during lock period
 * • Reentrancy-guarded throughout
 *
 * Deploy constructor args:
 *   _obxToken          – OBX BEP-20/ERC-20 contract (must support burn())
 *   _burnOnStakeBps    – e.g. 100 = 1 %
 *   _burnOnUnstakeBps  – e.g. 200 = 2 %
 *
 * After deploy:
 *   addPool(...)        – add staking tiers
 *   fundRewards(amount) – top-up reward pool (owner approves first)
 */

interface IBurnableOBX {
    function totalSupply() external view returns (uint256);
    function balanceOf(address account) external view returns (uint256);
    function transfer(address to, uint256 amount) external returns (bool);
    function transferFrom(address from, address to, uint256 amount) external returns (bool);
    function approve(address spender, uint256 amount) external returns (bool);
    function burn(uint256 amount) external;
}

abstract contract ReentrancyGuard {
    uint256 private constant _NOT_ENTERED = 1;
    uint256 private constant _ENTERED     = 2;
    uint256 private _status = _NOT_ENTERED;
    modifier nonReentrant() {
        require(_status != _ENTERED, "ReentrancyGuard: reentrant call");
        _status = _ENTERED;
        _;
        _status = _NOT_ENTERED;
    }
}

abstract contract Ownable {
    address private _owner;
    event OwnershipTransferred(address indexed previousOwner, address indexed newOwner);
    constructor(address initialOwner) {
        require(initialOwner != address(0), "Ownable: zero address");
        _owner = initialOwner;
        emit OwnershipTransferred(address(0), initialOwner);
    }
    modifier onlyOwner() {
        require(msg.sender == _owner, "Ownable: caller is not the owner");
        _;
    }
    function owner() public view returns (address) { return _owner; }
    function transferOwnership(address newOwner) external onlyOwner {
        require(newOwner != address(0), "Ownable: zero address");
        emit OwnershipTransferred(_owner, newOwner);
        _owner = newOwner;
    }
}

contract OBXStaking is Ownable, ReentrancyGuard {

    // ── Token ────────────────────────────────────────────────────────────────
    IBurnableOBX public immutable obxToken;

    // ── Burn rates (basis points, 10 000 = 100 %) ────────────────────────────
    uint256 public burnOnStakeBps;    // burned from gross amount when staking
    uint256 public burnOnUnstakeBps;  // burned from principal when unstaking

    // ── Pools ────────────────────────────────────────────────────────────────
    struct Pool {
        string  name;
        uint256 minAmount;      // minimum gross OBX (18 dec wei) to enter pool
        uint256 durationDays;   // lock period
        uint256 apyBps;         // APY in basis points  (500 = 5 %)
        bool    active;
    }
    Pool[]  public pools;

    // ── Per-user stakes ──────────────────────────────────────────────────────
    struct StakeRecord {
        uint256 poolId;
        uint256 netAmount;   // staked after burn
        uint256 stakedAt;
        uint256 lockUntil;
        uint256 rewardPaid;  // reward already claimed/paid
        bool    active;
    }
    mapping(address => StakeRecord[]) public stakes;

    // ── Reward reserve ───────────────────────────────────────────────────────
    uint256 public rewardReserve;

    // ── Total stats ──────────────────────────────────────────────────────────
    uint256 public totalStaked;
    uint256 public totalBurned;
    uint256 public totalRewardsPaid;

    // ── Events ───────────────────────────────────────────────────────────────

    event PoolAdded(
        uint256 indexed poolId,
        string  name,
        uint256 minAmount,
        uint256 durationDays,
        uint256 apyBps
    );

    event PoolUpdated(uint256 indexed poolId, bool active);

    event BurnRatesUpdated(uint256 onStakeBps, uint256 onUnstakeBps);

    /**
     * Emitted when a user stakes.
     * grossAmount  = tokens transferred from user
     * burnedAmount = tokens burned immediately (= grossAmount - netAmount)
     * netAmount    = tokens held in contract for this stake
     */
    event Staked(
        address indexed user,
        uint256 indexed stakeIdx,
        uint256 indexed poolId,
        uint256 grossAmount,
        uint256 burnedAmount,
        uint256 netAmount,
        uint256 lockedAt,
        uint256 lockUntil
    );

    /**
     * Emitted when a user unstakes.
     * principal      = net staked amount returned (after unstake burn)
     * burnedAmount   = tokens burned on unstake
     * rewardAmount   = reward tokens paid out
     * totalReturned  = principal + rewardAmount
     */
    event Unstaked(
        address indexed user,
        uint256 indexed stakeIdx,
        uint256 grossPrincipal,
        uint256 burnedAmount,
        uint256 principal,
        uint256 rewardAmount,
        uint256 totalReturned
    );

    event RewardsFunded(address indexed by, uint256 amount);

    // ── Constructor ──────────────────────────────────────────────────────────

    constructor(
        address _obxToken,
        uint256 _burnOnStakeBps,
        uint256 _burnOnUnstakeBps
    ) Ownable(msg.sender) {
        require(_obxToken != address(0),          "zero token");
        require(_burnOnStakeBps   <= 1_000,       "max 10 pct stake burn");
        require(_burnOnUnstakeBps <= 1_000,       "max 10 pct unstake burn");
        obxToken           = IBurnableOBX(_obxToken);
        burnOnStakeBps     = _burnOnStakeBps;
        burnOnUnstakeBps   = _burnOnUnstakeBps;
    }

    // ── Pool management ──────────────────────────────────────────────────────

    /**
     * Add a new staking pool tier.
     * @param _name         Human-readable name, e.g. "Silver – 30 Days"
     * @param _minAmount    Minimum gross stake in wei (18 decimals)
     * @param _durationDays Lock period in days (e.g. 30, 90, 180)
     * @param _apyBps       APY in basis points (e.g. 500 = 5 %)
     */
    function addPool(
        string calldata _name,
        uint256 _minAmount,
        uint256 _durationDays,
        uint256 _apyBps
    ) external onlyOwner {
        require(_durationDays > 0,   "zero duration");
        require(_apyBps <= 100_000,  "APY unreasonable");  // max 1 000 %
        pools.push(Pool({
            name:         _name,
            minAmount:    _minAmount,
            durationDays: _durationDays,
            apyBps:       _apyBps,
            active:       true
        }));
        emit PoolAdded(pools.length - 1, _name, _minAmount, _durationDays, _apyBps);
    }

    function setPoolActive(uint256 poolId, bool _active) external onlyOwner {
        require(poolId < pools.length, "bad pool");
        pools[poolId].active = _active;
        emit PoolUpdated(poolId, _active);
    }

    function setBurnRates(uint256 _onStakeBps, uint256 _onUnstakeBps) external onlyOwner {
        require(_onStakeBps   <= 1_000, "max 10 pct");
        require(_onUnstakeBps <= 1_000, "max 10 pct");
        burnOnStakeBps   = _onStakeBps;
        burnOnUnstakeBps = _onUnstakeBps;
        emit BurnRatesUpdated(_onStakeBps, _onUnstakeBps);
    }

    /**
     * Fund the reward reserve. Owner must approve this contract first.
     */
    function fundRewards(uint256 amount) external onlyOwner {
        require(amount > 0, "zero amount");
        obxToken.transferFrom(msg.sender, address(this), amount);
        rewardReserve += amount;
        emit RewardsFunded(msg.sender, amount);
    }

    // ── Stake ────────────────────────────────────────────────────────────────

    /**
     * Stake OBX into a pool.
     * User must first approve this contract for `grossAmount`.
     *
     * @param poolId       Index in the pools array
     * @param grossAmount  Gross OBX to stake (18-decimal wei).
     *                     burnOnStakeBps % is burned; the rest is held.
     */
    function stake(uint256 poolId, uint256 grossAmount) external nonReentrant {
        require(poolId < pools.length,          "bad pool");
        Pool storage pool = pools[poolId];
        require(pool.active,                    "pool inactive");
        require(grossAmount >= pool.minAmount,  "below minimum");

        // Pull OBX from user
        require(
            obxToken.transferFrom(msg.sender, address(this), grossAmount),
            "transfer failed"
        );

        // Burn on stake
        uint256 burnAmt = (grossAmount * burnOnStakeBps) / 10_000;
        uint256 netAmt  = grossAmount - burnAmt;
        if (burnAmt > 0) {
            obxToken.burn(burnAmt);
            totalBurned += burnAmt;
        }

        // Record stake
        uint256 now_    = block.timestamp;
        uint256 lockEnd = now_ + (pool.durationDays * 1 days);
        uint256 idx     = stakes[msg.sender].length;

        stakes[msg.sender].push(StakeRecord({
            poolId:     poolId,
            netAmount:  netAmt,
            stakedAt:   now_,
            lockUntil:  lockEnd,
            rewardPaid: 0,
            active:     true
        }));

        totalStaked += netAmt;

        emit Staked(
            msg.sender, idx, poolId,
            grossAmount, burnAmt, netAmt,
            now_, lockEnd
        );
    }

    // ── Unstake ──────────────────────────────────────────────────────────────

    /**
     * Unstake after lock period expires.
     * Burns burnOnUnstakeBps % of the principal, then returns remainder + reward.
     *
     * @param stakeIdx  Index in stakes[msg.sender]
     */
    function unstake(uint256 stakeIdx) external nonReentrant {
        require(stakeIdx < stakes[msg.sender].length, "bad index");
        StakeRecord storage rec = stakes[msg.sender][stakeIdx];
        require(rec.active,                     "not active");
        require(block.timestamp >= rec.lockUntil, "still locked");

        rec.active = false;

        uint256 principal = rec.netAmount;
        uint256 reward    = _calcReward(rec);

        // Burn on unstake
        uint256 burnAmt   = (principal * burnOnUnstakeBps) / 10_000;
        uint256 returnAmt = principal - burnAmt;
        if (burnAmt > 0) {
            obxToken.burn(burnAmt);
            totalBurned  += burnAmt;
            totalStaked  -= (totalStaked >= principal ? principal : totalStaked);
        } else {
            totalStaked  -= (totalStaked >= principal ? principal : totalStaked);
        }

        // Pay reward from reserve
        if (reward > 0) {
            require(rewardReserve >= reward, "reward reserve empty");
            rewardReserve     -= reward;
            totalRewardsPaid  += reward;
            rec.rewardPaid     = reward;
        }

        uint256 total = returnAmt + reward;
        require(
            obxToken.transfer(msg.sender, total),
            "return transfer failed"
        );

        emit Unstaked(
            msg.sender, stakeIdx,
            principal, burnAmt, returnAmt,
            reward, total
        );
    }

    // ── Views ─────────────────────────────────────────────────────────────────

    /**
     * Calculate accrued reward for a stake (live, at current timestamp).
     */
    function calculateReward(address user, uint256 stakeIdx) external view returns (uint256) {
        require(stakeIdx < stakes[user].length, "bad index");
        return _calcReward(stakes[user][stakeIdx]);
    }

    function getStake(address user, uint256 idx) external view returns (StakeRecord memory) {
        return stakes[user][idx];
    }

    function getStakeCount(address user) external view returns (uint256) {
        return stakes[user].length;
    }

    function getPool(uint256 poolId) external view returns (Pool memory) {
        require(poolId < pools.length, "bad pool");
        return pools[poolId];
    }

    function getPoolCount() external view returns (uint256) {
        return pools.length;
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    /**
     * Pro-rata APY reward.
     * reward = netAmount * apyBps * elapsed / (365 days * 10_000)
     */
    function _calcReward(StakeRecord memory rec) internal view returns (uint256) {
        if (!rec.active) return 0;
        Pool memory pool    = pools[rec.poolId];
        uint256 elapsed     = block.timestamp - rec.stakedAt;
        uint256 fullPeriod  = pool.durationDays * 1 days;
        // Cap elapsed at duration so reward doesn't exceed APY promise
        if (elapsed > fullPeriod) elapsed = fullPeriod;
        // reward = netAmount * apyBps * elapsed / (365_days * 10_000)
        return (rec.netAmount * pool.apyBps * elapsed) / (365 days * 10_000);
    }
}
