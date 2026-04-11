# Code Citations

## License: unknown
https://github.com/bznsix/bsc-contract-database/blob/0d203843dd60f461575e48ff40fe856eb9a88c40/2024-01-03/0x65e536A7c9B7ed8c72f3F3AF5d6Df6AA2d1de5F8.sol

```
I've done a thorough read of the codebase. Here's the full plan:

---

## How ICO Currently Works in This Codebase

### Architecture Summary

The system is entirely **off-chain / custodial** — there is no smart contract. OBXCoin exists only as a database balance (`wallets.balance`). The ICO mechanism works like this:

| Component | Description |
|---|---|
| `ico_phases` table | Stores presale rounds: name, start/end date, rate (price in USD), amount (token cap per phase), fees %, bonus %, affiliation config |
| `buy_coin_histories` table | Purchase orders — stores user, coin amount, USD paid, payment tx, phase_id, bonus, fees, status (pending → approved) |
| `checkAvailableBuyPhase()` | Finds the currently active phase by date; returns future phase info if none active yet |
| `CoinController@buyCoin` | Validates phase cap, calculates fees/bonus, routes to payment handler |
| `CoinRepository` | 3 payment methods: **CoinPayments** (BTC/crypto), **Bank transfer** (manual slip), **Stripe** (USD card) |
| Admin approval | After crypto/bank payment, admin manually confirms, coins are credited to user's default wallet |
| `GiveCoin` job | Queue job that debits sender and credits receiver atomically |

**The 0.05% burn** is conceptual/database-level — every internal transfer should deduct 0.05% and subtract it from total supply (not yet implemented).

---

## Presale Smart Contract Plan

### Goal
A Solidity presale contract that:
1. Accepts ETH/USDT/USDC payments for OBXCoin tokens
2. Integrates with OBXCoin's deflationary ERC-20 (0.05% burn per transfer)
3. Maps phases (rate, cap, dates, bonus) — mirrors the existing `ico_phases` table
4. Can be plugged into this Laravel app alongside the existing off-chain flow

---

### Contract Architecture

#### 1. `OBXCoin.sol` — Deflationary ERC-20

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract OBXCoin is ERC20, Ownable {
    uint256 public constant BURN_BASIS_POINTS = 5;  // 0.05% = 5/10000
    address public presaleContract;

    constructor(uint256 initialSupply) ERC20("OBXCoin", "OBX") Ownable(msg.sender) {
        _mint(msg.sender, initialSupply * 10 ** decimals());
    }

    function setPresaleContract(address _presale) external onlyOwner {
        presaleContract = _presale;
    }

    // Override transfer to apply 0.05% burn on every transfer
    function _update(address from, address to, uint256 amount) internal override {
        // No burn on minting, burning itself, or presale contract transfers
        if (from == address(0) || to == address(0) || from == presaleContract) {
            super._update(from, to, amount);
            return;
        }

        uint256 burnAmount = (amount * BURN_BASIS_POINTS) / 10000;
        uint256 sendAmount = amount - burnAmount;

        super._update(from, address(0), burnAmount); // burn
        super._update(from, to, sendAmount);          // transfer net amount
    }
}
```

**Key design decisions:**
- Uses OpenZeppelin `ERC20._update()` override (OZ v5, compatible with Solidity 0.8.20+)
- `BURN_BASIS_POINTS = 5` → `5/10000 = 0.05%`
- Burn is skipped when presale contract transfers tokens to buyers (so buyer gets the full amount they paid for)
- Burn fires on every peer-to-peer transfer, wallet send, DEX trade, etc.

---

#### 2. `OBXPresale.sol` — Phase-Based Presale

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";

contract OBXPresale is Ownable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    IERC20 public obxToken;
    IERC20 
```


## License: unknown
https://github.com/bznsix/bsc-contract-database/blob/0d203843dd60f461575e48ff40fe856eb9a88c40/2024-01-03/0x65e536A7c9B7ed8c72f3F3AF5d6Df6AA2d1de5F8.sol

```
I've done a thorough read of the codebase. Here's the full plan:

---

## How ICO Currently Works in This Codebase

### Architecture Summary

The system is entirely **off-chain / custodial** — there is no smart contract. OBXCoin exists only as a database balance (`wallets.balance`). The ICO mechanism works like this:

| Component | Description |
|---|---|
| `ico_phases` table | Stores presale rounds: name, start/end date, rate (price in USD), amount (token cap per phase), fees %, bonus %, affiliation config |
| `buy_coin_histories` table | Purchase orders — stores user, coin amount, USD paid, payment tx, phase_id, bonus, fees, status (pending → approved) |
| `checkAvailableBuyPhase()` | Finds the currently active phase by date; returns future phase info if none active yet |
| `CoinController@buyCoin` | Validates phase cap, calculates fees/bonus, routes to payment handler |
| `CoinRepository` | 3 payment methods: **CoinPayments** (BTC/crypto), **Bank transfer** (manual slip), **Stripe** (USD card) |
| Admin approval | After crypto/bank payment, admin manually confirms, coins are credited to user's default wallet |
| `GiveCoin` job | Queue job that debits sender and credits receiver atomically |

**The 0.05% burn** is conceptual/database-level — every internal transfer should deduct 0.05% and subtract it from total supply (not yet implemented).

---

## Presale Smart Contract Plan

### Goal
A Solidity presale contract that:
1. Accepts ETH/USDT/USDC payments for OBXCoin tokens
2. Integrates with OBXCoin's deflationary ERC-20 (0.05% burn per transfer)
3. Maps phases (rate, cap, dates, bonus) — mirrors the existing `ico_phases` table
4. Can be plugged into this Laravel app alongside the existing off-chain flow

---

### Contract Architecture

#### 1. `OBXCoin.sol` — Deflationary ERC-20

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract OBXCoin is ERC20, Ownable {
    uint256 public constant BURN_BASIS_POINTS = 5;  // 0.05% = 5/10000
    address public presaleContract;

    constructor(uint256 initialSupply) ERC20("OBXCoin", "OBX") Ownable(msg.sender) {
        _mint(msg.sender, initialSupply * 10 ** decimals());
    }

    function setPresaleContract(address _presale) external onlyOwner {
        presaleContract = _presale;
    }

    // Override transfer to apply 0.05% burn on every transfer
    function _update(address from, address to, uint256 amount) internal override {
        // No burn on minting, burning itself, or presale contract transfers
        if (from == address(0) || to == address(0) || from == presaleContract) {
            super._update(from, to, amount);
            return;
        }

        uint256 burnAmount = (amount * BURN_BASIS_POINTS) / 10000;
        uint256 sendAmount = amount - burnAmount;

        super._update(from, address(0), burnAmount); // burn
        super._update(from, to, sendAmount);          // transfer net amount
    }
}
```

**Key design decisions:**
- Uses OpenZeppelin `ERC20._update()` override (OZ v5, compatible with Solidity 0.8.20+)
- `BURN_BASIS_POINTS = 5` → `5/10000 = 0.05%`
- Burn is skipped when presale contract transfers tokens to buyers (so buyer gets the full amount they paid for)
- Burn fires on every peer-to-peer transfer, wallet send, DEX trade, etc.

---

#### 2. `OBXPresale.sol` — Phase-Based Presale

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";

contract OBXPresale is Ownable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    IERC20 public obxToken;
    IERC20 
```


## License: unknown
https://github.com/bznsix/bsc-contract-database/blob/0d203843dd60f461575e48ff40fe856eb9a88c40/2024-01-03/0x65e536A7c9B7ed8c72f3F3AF5d6Df6AA2d1de5F8.sol

```
I've done a thorough read of the codebase. Here's the full plan:

---

## How ICO Currently Works in This Codebase

### Architecture Summary

The system is entirely **off-chain / custodial** — there is no smart contract. OBXCoin exists only as a database balance (`wallets.balance`). The ICO mechanism works like this:

| Component | Description |
|---|---|
| `ico_phases` table | Stores presale rounds: name, start/end date, rate (price in USD), amount (token cap per phase), fees %, bonus %, affiliation config |
| `buy_coin_histories` table | Purchase orders — stores user, coin amount, USD paid, payment tx, phase_id, bonus, fees, status (pending → approved) |
| `checkAvailableBuyPhase()` | Finds the currently active phase by date; returns future phase info if none active yet |
| `CoinController@buyCoin` | Validates phase cap, calculates fees/bonus, routes to payment handler |
| `CoinRepository` | 3 payment methods: **CoinPayments** (BTC/crypto), **Bank transfer** (manual slip), **Stripe** (USD card) |
| Admin approval | After crypto/bank payment, admin manually confirms, coins are credited to user's default wallet |
| `GiveCoin` job | Queue job that debits sender and credits receiver atomically |

**The 0.05% burn** is conceptual/database-level — every internal transfer should deduct 0.05% and subtract it from total supply (not yet implemented).

---

## Presale Smart Contract Plan

### Goal
A Solidity presale contract that:
1. Accepts ETH/USDT/USDC payments for OBXCoin tokens
2. Integrates with OBXCoin's deflationary ERC-20 (0.05% burn per transfer)
3. Maps phases (rate, cap, dates, bonus) — mirrors the existing `ico_phases` table
4. Can be plugged into this Laravel app alongside the existing off-chain flow

---

### Contract Architecture

#### 1. `OBXCoin.sol` — Deflationary ERC-20

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract OBXCoin is ERC20, Ownable {
    uint256 public constant BURN_BASIS_POINTS = 5;  // 0.05% = 5/10000
    address public presaleContract;

    constructor(uint256 initialSupply) ERC20("OBXCoin", "OBX") Ownable(msg.sender) {
        _mint(msg.sender, initialSupply * 10 ** decimals());
    }

    function setPresaleContract(address _presale) external onlyOwner {
        presaleContract = _presale;
    }

    // Override transfer to apply 0.05% burn on every transfer
    function _update(address from, address to, uint256 amount) internal override {
        // No burn on minting, burning itself, or presale contract transfers
        if (from == address(0) || to == address(0) || from == presaleContract) {
            super._update(from, to, amount);
            return;
        }

        uint256 burnAmount = (amount * BURN_BASIS_POINTS) / 10000;
        uint256 sendAmount = amount - burnAmount;

        super._update(from, address(0), burnAmount); // burn
        super._update(from, to, sendAmount);          // transfer net amount
    }
}
```

**Key design decisions:**
- Uses OpenZeppelin `ERC20._update()` override (OZ v5, compatible with Solidity 0.8.20+)
- `BURN_BASIS_POINTS = 5` → `5/10000 = 0.05%`
- Burn is skipped when presale contract transfers tokens to buyers (so buyer gets the full amount they paid for)
- Burn fires on every peer-to-peer transfer, wallet send, DEX trade, etc.

---

#### 2. `OBXPresale.sol` — Phase-Based Presale

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";

contract OBXPresale is Ownable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    IERC20 public obxToken;
    IERC20 
```


## License: unknown
https://github.com/bznsix/bsc-contract-database/blob/0d203843dd60f461575e48ff40fe856eb9a88c40/2024-01-03/0x65e536A7c9B7ed8c72f3F3AF5d6Df6AA2d1de5F8.sol

```
I've done a thorough read of the codebase. Here's the full plan:

---

## How ICO Currently Works in This Codebase

### Architecture Summary

The system is entirely **off-chain / custodial** — there is no smart contract. OBXCoin exists only as a database balance (`wallets.balance`). The ICO mechanism works like this:

| Component | Description |
|---|---|
| `ico_phases` table | Stores presale rounds: name, start/end date, rate (price in USD), amount (token cap per phase), fees %, bonus %, affiliation config |
| `buy_coin_histories` table | Purchase orders — stores user, coin amount, USD paid, payment tx, phase_id, bonus, fees, status (pending → approved) |
| `checkAvailableBuyPhase()` | Finds the currently active phase by date; returns future phase info if none active yet |
| `CoinController@buyCoin` | Validates phase cap, calculates fees/bonus, routes to payment handler |
| `CoinRepository` | 3 payment methods: **CoinPayments** (BTC/crypto), **Bank transfer** (manual slip), **Stripe** (USD card) |
| Admin approval | After crypto/bank payment, admin manually confirms, coins are credited to user's default wallet |
| `GiveCoin` job | Queue job that debits sender and credits receiver atomically |

**The 0.05% burn** is conceptual/database-level — every internal transfer should deduct 0.05% and subtract it from total supply (not yet implemented).

---

## Presale Smart Contract Plan

### Goal
A Solidity presale contract that:
1. Accepts ETH/USDT/USDC payments for OBXCoin tokens
2. Integrates with OBXCoin's deflationary ERC-20 (0.05% burn per transfer)
3. Maps phases (rate, cap, dates, bonus) — mirrors the existing `ico_phases` table
4. Can be plugged into this Laravel app alongside the existing off-chain flow

---

### Contract Architecture

#### 1. `OBXCoin.sol` — Deflationary ERC-20

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract OBXCoin is ERC20, Ownable {
    uint256 public constant BURN_BASIS_POINTS = 5;  // 0.05% = 5/10000
    address public presaleContract;

    constructor(uint256 initialSupply) ERC20("OBXCoin", "OBX") Ownable(msg.sender) {
        _mint(msg.sender, initialSupply * 10 ** decimals());
    }

    function setPresaleContract(address _presale) external onlyOwner {
        presaleContract = _presale;
    }

    // Override transfer to apply 0.05% burn on every transfer
    function _update(address from, address to, uint256 amount) internal override {
        // No burn on minting, burning itself, or presale contract transfers
        if (from == address(0) || to == address(0) || from == presaleContract) {
            super._update(from, to, amount);
            return;
        }

        uint256 burnAmount = (amount * BURN_BASIS_POINTS) / 10000;
        uint256 sendAmount = amount - burnAmount;

        super._update(from, address(0), burnAmount); // burn
        super._update(from, to, sendAmount);          // transfer net amount
    }
}
```

**Key design decisions:**
- Uses OpenZeppelin `ERC20._update()` override (OZ v5, compatible with Solidity 0.8.20+)
- `BURN_BASIS_POINTS = 5` → `5/10000 = 0.05%`
- Burn is skipped when presale contract transfers tokens to buyers (so buyer gets the full amount they paid for)
- Burn fires on every peer-to-peer transfer, wallet send, DEX trade, etc.

---

#### 2. `OBXPresale.sol` — Phase-Based Presale

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";

contract OBXPresale is Ownable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    IERC20 public obxToken;
    IERC20 
```


## License: unknown
https://github.com/bznsix/bsc-contract-database/blob/0d203843dd60f461575e48ff40fe856eb9a88c40/2024-01-03/0x65e536A7c9B7ed8c72f3F3AF5d6Df6AA2d1de5F8.sol

```
I've done a thorough read of the codebase. Here's the full plan:

---

## How ICO Currently Works in This Codebase

### Architecture Summary

The system is entirely **off-chain / custodial** — there is no smart contract. OBXCoin exists only as a database balance (`wallets.balance`). The ICO mechanism works like this:

| Component | Description |
|---|---|
| `ico_phases` table | Stores presale rounds: name, start/end date, rate (price in USD), amount (token cap per phase), fees %, bonus %, affiliation config |
| `buy_coin_histories` table | Purchase orders — stores user, coin amount, USD paid, payment tx, phase_id, bonus, fees, status (pending → approved) |
| `checkAvailableBuyPhase()` | Finds the currently active phase by date; returns future phase info if none active yet |
| `CoinController@buyCoin` | Validates phase cap, calculates fees/bonus, routes to payment handler |
| `CoinRepository` | 3 payment methods: **CoinPayments** (BTC/crypto), **Bank transfer** (manual slip), **Stripe** (USD card) |
| Admin approval | After crypto/bank payment, admin manually confirms, coins are credited to user's default wallet |
| `GiveCoin` job | Queue job that debits sender and credits receiver atomically |

**The 0.05% burn** is conceptual/database-level — every internal transfer should deduct 0.05% and subtract it from total supply (not yet implemented).

---

## Presale Smart Contract Plan

### Goal
A Solidity presale contract that:
1. Accepts ETH/USDT/USDC payments for OBXCoin tokens
2. Integrates with OBXCoin's deflationary ERC-20 (0.05% burn per transfer)
3. Maps phases (rate, cap, dates, bonus) — mirrors the existing `ico_phases` table
4. Can be plugged into this Laravel app alongside the existing off-chain flow

---

### Contract Architecture

#### 1. `OBXCoin.sol` — Deflationary ERC-20

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract OBXCoin is ERC20, Ownable {
    uint256 public constant BURN_BASIS_POINTS = 5;  // 0.05% = 5/10000
    address public presaleContract;

    constructor(uint256 initialSupply) ERC20("OBXCoin", "OBX") Ownable(msg.sender) {
        _mint(msg.sender, initialSupply * 10 ** decimals());
    }

    function setPresaleContract(address _presale) external onlyOwner {
        presaleContract = _presale;
    }

    // Override transfer to apply 0.05% burn on every transfer
    function _update(address from, address to, uint256 amount) internal override {
        // No burn on minting, burning itself, or presale contract transfers
        if (from == address(0) || to == address(0) || from == presaleContract) {
            super._update(from, to, amount);
            return;
        }

        uint256 burnAmount = (amount * BURN_BASIS_POINTS) / 10000;
        uint256 sendAmount = amount - burnAmount;

        super._update(from, address(0), burnAmount); // burn
        super._update(from, to, sendAmount);          // transfer net amount
    }
}
```

**Key design decisions:**
- Uses OpenZeppelin `ERC20._update()` override (OZ v5, compatible with Solidity 0.8.20+)
- `BURN_BASIS_POINTS = 5` → `5/10000 = 0.05%`
- Burn is skipped when presale contract transfers tokens to buyers (so buyer gets the full amount they paid for)
- Burn fires on every peer-to-peer transfer, wallet send, DEX trade, etc.

---

#### 2. `OBXPresale.sol` — Phase-Based Presale

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";

contract OBXPresale is Ownable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    IERC20 public obxToken;
    IERC20 
```


## License: unknown
https://github.com/bznsix/bsc-contract-database/blob/0d203843dd60f461575e48ff40fe856eb9a88c40/2024-01-03/0x65e536A7c9B7ed8c72f3F3AF5d6Df6AA2d1de5F8.sol

```
I've done a thorough read of the codebase. Here's the full plan:

---

## How ICO Currently Works in This Codebase

### Architecture Summary

The system is entirely **off-chain / custodial** — there is no smart contract. OBXCoin exists only as a database balance (`wallets.balance`). The ICO mechanism works like this:

| Component | Description |
|---|---|
| `ico_phases` table | Stores presale rounds: name, start/end date, rate (price in USD), amount (token cap per phase), fees %, bonus %, affiliation config |
| `buy_coin_histories` table | Purchase orders — stores user, coin amount, USD paid, payment tx, phase_id, bonus, fees, status (pending → approved) |
| `checkAvailableBuyPhase()` | Finds the currently active phase by date; returns future phase info if none active yet |
| `CoinController@buyCoin` | Validates phase cap, calculates fees/bonus, routes to payment handler |
| `CoinRepository` | 3 payment methods: **CoinPayments** (BTC/crypto), **Bank transfer** (manual slip), **Stripe** (USD card) |
| Admin approval | After crypto/bank payment, admin manually confirms, coins are credited to user's default wallet |
| `GiveCoin` job | Queue job that debits sender and credits receiver atomically |

**The 0.05% burn** is conceptual/database-level — every internal transfer should deduct 0.05% and subtract it from total supply (not yet implemented).

---

## Presale Smart Contract Plan

### Goal
A Solidity presale contract that:
1. Accepts ETH/USDT/USDC payments for OBXCoin tokens
2. Integrates with OBXCoin's deflationary ERC-20 (0.05% burn per transfer)
3. Maps phases (rate, cap, dates, bonus) — mirrors the existing `ico_phases` table
4. Can be plugged into this Laravel app alongside the existing off-chain flow

---

### Contract Architecture

#### 1. `OBXCoin.sol` — Deflationary ERC-20

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract OBXCoin is ERC20, Ownable {
    uint256 public constant BURN_BASIS_POINTS = 5;  // 0.05% = 5/10000
    address public presaleContract;

    constructor(uint256 initialSupply) ERC20("OBXCoin", "OBX") Ownable(msg.sender) {
        _mint(msg.sender, initialSupply * 10 ** decimals());
    }

    function setPresaleContract(address _presale) external onlyOwner {
        presaleContract = _presale;
    }

    // Override transfer to apply 0.05% burn on every transfer
    function _update(address from, address to, uint256 amount) internal override {
        // No burn on minting, burning itself, or presale contract transfers
        if (from == address(0) || to == address(0) || from == presaleContract) {
            super._update(from, to, amount);
            return;
        }

        uint256 burnAmount = (amount * BURN_BASIS_POINTS) / 10000;
        uint256 sendAmount = amount - burnAmount;

        super._update(from, address(0), burnAmount); // burn
        super._update(from, to, sendAmount);          // transfer net amount
    }
}
```

**Key design decisions:**
- Uses OpenZeppelin `ERC20._update()` override (OZ v5, compatible with Solidity 0.8.20+)
- `BURN_BASIS_POINTS = 5` → `5/10000 = 0.05%`
- Burn is skipped when presale contract transfers tokens to buyers (so buyer gets the full amount they paid for)
- Burn fires on every peer-to-peer transfer, wallet send, DEX trade, etc.

---

#### 2. `OBXPresale.sol` — Phase-Based Presale

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";

contract OBXPresale is Ownable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    IERC20 public obxToken;
    IERC20 
```


## License: unknown
https://github.com/bznsix/bsc-contract-database/blob/0d203843dd60f461575e48ff40fe856eb9a88c40/2024-01-03/0x65e536A7c9B7ed8c72f3F3AF5d6Df6AA2d1de5F8.sol

```
I've done a thorough read of the codebase. Here's the full plan:

---

## How ICO Currently Works in This Codebase

### Architecture Summary

The system is entirely **off-chain / custodial** — there is no smart contract. OBXCoin exists only as a database balance (`wallets.balance`). The ICO mechanism works like this:

| Component | Description |
|---|---|
| `ico_phases` table | Stores presale rounds: name, start/end date, rate (price in USD), amount (token cap per phase), fees %, bonus %, affiliation config |
| `buy_coin_histories` table | Purchase orders — stores user, coin amount, USD paid, payment tx, phase_id, bonus, fees, status (pending → approved) |
| `checkAvailableBuyPhase()` | Finds the currently active phase by date; returns future phase info if none active yet |
| `CoinController@buyCoin` | Validates phase cap, calculates fees/bonus, routes to payment handler |
| `CoinRepository` | 3 payment methods: **CoinPayments** (BTC/crypto), **Bank transfer** (manual slip), **Stripe** (USD card) |
| Admin approval | After crypto/bank payment, admin manually confirms, coins are credited to user's default wallet |
| `GiveCoin` job | Queue job that debits sender and credits receiver atomically |

**The 0.05% burn** is conceptual/database-level — every internal transfer should deduct 0.05% and subtract it from total supply (not yet implemented).

---

## Presale Smart Contract Plan

### Goal
A Solidity presale contract that:
1. Accepts ETH/USDT/USDC payments for OBXCoin tokens
2. Integrates with OBXCoin's deflationary ERC-20 (0.05% burn per transfer)
3. Maps phases (rate, cap, dates, bonus) — mirrors the existing `ico_phases` table
4. Can be plugged into this Laravel app alongside the existing off-chain flow

---

### Contract Architecture

#### 1. `OBXCoin.sol` — Deflationary ERC-20

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract OBXCoin is ERC20, Ownable {
    uint256 public constant BURN_BASIS_POINTS = 5;  // 0.05% = 5/10000
    address public presaleContract;

    constructor(uint256 initialSupply) ERC20("OBXCoin", "OBX") Ownable(msg.sender) {
        _mint(msg.sender, initialSupply * 10 ** decimals());
    }

    function setPresaleContract(address _presale) external onlyOwner {
        presaleContract = _presale;
    }

    // Override transfer to apply 0.05% burn on every transfer
    function _update(address from, address to, uint256 amount) internal override {
        // No burn on minting, burning itself, or presale contract transfers
        if (from == address(0) || to == address(0) || from == presaleContract) {
            super._update(from, to, amount);
            return;
        }

        uint256 burnAmount = (amount * BURN_BASIS_POINTS) / 10000;
        uint256 sendAmount = amount - burnAmount;

        super._update(from, address(0), burnAmount); // burn
        super._update(from, to, sendAmount);          // transfer net amount
    }
}
```

**Key design decisions:**
- Uses OpenZeppelin `ERC20._update()` override (OZ v5, compatible with Solidity 0.8.20+)
- `BURN_BASIS_POINTS = 5` → `5/10000 = 0.05%`
- Burn is skipped when presale contract transfers tokens to buyers (so buyer gets the full amount they paid for)
- Burn fires on every peer-to-peer transfer, wallet send, DEX trade, etc.

---

#### 2. `OBXPresale.sol` — Phase-Based Presale

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";

contract OBXPresale is Ownable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    IERC20 public obxToken;
    IERC20 
```


## License: unknown
https://github.com/bznsix/bsc-contract-database/blob/0d203843dd60f461575e48ff40fe856eb9a88c40/2024-01-03/0x65e536A7c9B7ed8c72f3F3AF5d6Df6AA2d1de5F8.sol

```
I've done a thorough read of the codebase. Here's the full plan:

---

## How ICO Currently Works in This Codebase

### Architecture Summary

The system is entirely **off-chain / custodial** — there is no smart contract. OBXCoin exists only as a database balance (`wallets.balance`). The ICO mechanism works like this:

| Component | Description |
|---|---|
| `ico_phases` table | Stores presale rounds: name, start/end date, rate (price in USD), amount (token cap per phase), fees %, bonus %, affiliation config |
| `buy_coin_histories` table | Purchase orders — stores user, coin amount, USD paid, payment tx, phase_id, bonus, fees, status (pending → approved) |
| `checkAvailableBuyPhase()` | Finds the currently active phase by date; returns future phase info if none active yet |
| `CoinController@buyCoin` | Validates phase cap, calculates fees/bonus, routes to payment handler |
| `CoinRepository` | 3 payment methods: **CoinPayments** (BTC/crypto), **Bank transfer** (manual slip), **Stripe** (USD card) |
| Admin approval | After crypto/bank payment, admin manually confirms, coins are credited to user's default wallet |
| `GiveCoin` job | Queue job that debits sender and credits receiver atomically |

**The 0.05% burn** is conceptual/database-level — every internal transfer should deduct 0.05% and subtract it from total supply (not yet implemented).

---

## Presale Smart Contract Plan

### Goal
A Solidity presale contract that:
1. Accepts ETH/USDT/USDC payments for OBXCoin tokens
2. Integrates with OBXCoin's deflationary ERC-20 (0.05% burn per transfer)
3. Maps phases (rate, cap, dates, bonus) — mirrors the existing `ico_phases` table
4. Can be plugged into this Laravel app alongside the existing off-chain flow

---

### Contract Architecture

#### 1. `OBXCoin.sol` — Deflationary ERC-20

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract OBXCoin is ERC20, Ownable {
    uint256 public constant BURN_BASIS_POINTS = 5;  // 0.05% = 5/10000
    address public presaleContract;

    constructor(uint256 initialSupply) ERC20("OBXCoin", "OBX") Ownable(msg.sender) {
        _mint(msg.sender, initialSupply * 10 ** decimals());
    }

    function setPresaleContract(address _presale) external onlyOwner {
        presaleContract = _presale;
    }

    // Override transfer to apply 0.05% burn on every transfer
    function _update(address from, address to, uint256 amount) internal override {
        // No burn on minting, burning itself, or presale contract transfers
        if (from == address(0) || to == address(0) || from == presaleContract) {
            super._update(from, to, amount);
            return;
        }

        uint256 burnAmount = (amount * BURN_BASIS_POINTS) / 10000;
        uint256 sendAmount = amount - burnAmount;

        super._update(from, address(0), burnAmount); // burn
        super._update(from, to, sendAmount);          // transfer net amount
    }
}
```

**Key design decisions:**
- Uses OpenZeppelin `ERC20._update()` override (OZ v5, compatible with Solidity 0.8.20+)
- `BURN_BASIS_POINTS = 5` → `5/10000 = 0.05%`
- Burn is skipped when presale contract transfers tokens to buyers (so buyer gets the full amount they paid for)
- Burn fires on every peer-to-peer transfer, wallet send, DEX trade, etc.

---

#### 2. `OBXPresale.sol` — Phase-Based Presale

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";

contract OBXPresale is Ownable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    IERC20 public obxToken;
    IERC20 
```

