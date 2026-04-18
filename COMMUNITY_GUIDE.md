# OBXCoin Community Guide

Practical guide for community members, holders, and new users.

## 1) What Is OBXCoin?

OBXCoin is a blockchain-based ecosystem centered on OBX token utility, transparent on-chain activity, and community participation.

Core areas:
- OBX buying through presale/payment flows
- OBX wallet management (personal and team use cases)
- Staking and campaign participation
- Community growth through referrals and education

## 2) Utility First: Why OBX Exists

Utility is the most important part of OBXCoin.

OBX utility in practice:
- Payment and settlement token inside OBX ecosystem flows.
- Presale participation and structured distribution.
- Staking participation and reward access.
- Team and community operations with transparent on-chain traces.

Community principle:
- Price may change with market conditions.
- Utility, transparency, and adoption are the long-term foundation.

## 3) Official Contracts (BSC Mainnet)

Use these contracts for verification and education.

- OBX Token Contract: `0x045585578f5eB9966337Ec5F35C1E18F04b34F58`
- OBX Presale Contract: `0xDFb2448e3b540195bd63c18a7f22A5E45196D480`

Verification links:
- Token: https://bscscan.com/address/0x045585578f5eB9966337Ec5F35C1E18F04b34F58
- Presale: https://bscscan.com/address/0xDFb2448e3b540195bd63c18a7f22A5E45196D480

Always verify addresses from official OBX channels before transacting.

## 4) OBX Token Basics

OBX is designed with a deflationary transfer model.

Token highlights:
- Initial total supply: 100,000,000 OBX
- Decimals: 18
- Transfer burn: 0.05% per transfer (until floor condition)
- Burn floor: 41,000,000 OBX supply target

Burn math:
- Burned amount = Transfer amount x 0.0005
- Recipient amount = Transfer amount x 0.9995

Example:
- If 10,000 OBX is transferred on-chain:
- Burn = 5 OBX
- Recipient receives = 9,995 OBX

## 5) Tokenomics (Community View)

Published allocation model:

| Allocation | Amount | Share |
|---|---:|---:|
| Presale | 20,000,000 OBX | 20% |
| Airdrops | 5,000,000 OBX | 5% |
| Staking Rewards | 5,000,000 OBX | 5% |
| Team + Liquidity + Ops | 70,000,000 OBX | 70% |

How this benefits the community:
- Presale allocation supports early community entry.
- Airdrop allocation supports growth and awareness.
- Staking allocation supports long-term participation.
- Burn mechanism reduces circulating supply pressure over time.

## 6) Important Crediting Rule (Post-Burn Accurate)

OBXCoin now credits using the amount actually delivered on-chain, not just requested amount.

What this means:
- If a transfer burns 0.05%, your internal credited amount follows the on-chain received amount.
- System credit is aligned with smart contract reality.

How this is handled:
- Delivery amount is read from on-chain transaction data and wallet balance delta.
- If direct read is unavailable, burn-model fallback is used (`requested x 0.9995`).

## 7) Buying OBX: What Users Should Know

Current user flow emphasis:
- NOWPayments-based processing is supported for buy flow.
- Background sync finalizes crediting even if user closes/exits the payment page.

Background reliability:
- Pending payment statuses are synced by scheduled jobs.
- Failed deliveries are retried automatically.
- Users are not required to keep the payment tab open for final credit.

## 8) Wallets: Personal, Default, and Team

Default wallet principle:
- EVM delivery for OBX uses the user's default OBX wallet context.
- Address generation and delivery checks prioritize default coin type wallet paths.

Team wallet principle:
- Team withdrawal actions are traceable and auditable.
- On-chain hash references allow public verification of settlements.

## 9) Staking and Community Participation

Community members can increase utility through:
- Staking participation
- Referral growth
- Campaign participation (where active)

Best practice:
- Treat staking as long-term participation, not guaranteed short-term profit.
- Always check current APY, lock periods, and fee logic before confirming.

## 10) Referral Program (How to Use Responsibly)

Referral activity should focus on education-first growth:
- Share your code/link only with clear explanation.
- Avoid unrealistic promises.
- Help referrals understand wallets, security, and volatility.

Healthy community growth comes from retention and trust, not spam.

## 11) Security Checklist for Every Member

Account safety:
- Enable 2FA immediately.
- Use a unique, strong password.
- Never share OTP, password, or private keys.

Transaction safety:
- Double-check addresses before sending.
- Start with a small test transfer for new addresses.
- Keep transaction hashes for support/audit.

Scam prevention:
- Ignore DMs asking for wallet access.
- Verify official announcements from official channels only.
- No support agent should ever ask for private keys.

## 12) Community FAQ

Q: Why does received OBX differ slightly from requested transfer amount?
A: OBX transfer burn is 0.05% until burn floor conditions are reached.

Q: If I leave the payment page, will I still be credited?
A: Yes. Background sync and retry jobs process NOWPayments statuses and finalize delivery.

Q: Is credit based on requested amount or chain-delivered amount?
A: Crediting follows chain-delivered amount (post-burn accurate).

Q: Can I verify transfers publicly?
A: Yes, use transaction hash on the configured block explorer.

## 13) Risks and Responsibility

Please understand:
- Crypto assets are volatile.
- Regulations may change by region.
- Smart contract and infrastructure risks can exist.
- Never invest more than you can afford to lose.

This guide is educational and not financial advice.

## 14) Suggested Official Community Standards

To keep OBX community strong:
- Be helpful and factual.
- Do not mislead on price or returns.
- Report scams quickly.
- Focus on product utility, transparency, and education.

## 15) Whitepaper Technical Section (Ready to Reuse)

### 15.1 Protocol Layer Overview

OBXCoin operates on an EVM-compatible architecture composed of:
- OBX token contract (deflationary fungible token)
- Presale contract (phase-based distribution and optional auto-liquidity)
- Staking contract (pool-based lock and reward model)
- Airdrop contract (daily accrual with post-campaign unlock)
- Application settlement layer (NOWPayments + backend delivery + ledger sync)

This design separates token protocol rules (on-chain) from user settlement orchestration (off-chain service layer), while keeping final delivery and transfer evidence verifiable on-chain.

### 15.2 OBX Token Mechanics

OBX token uses 18 decimals and includes a deterministic transfer burn model.

Key parameters:
- Initial supply: 100,000,000 OBX
- Burn fee: 0.05% per transfer (5 basis points)
- Burn floor: 41,000,000 OBX
- Burn completion: once floor is reached, transfer burn remains disabled permanently

Transfer rule (while burn is active):
- Burned amount = transfer_amount x 0.0005
- Recipient amount = transfer_amount x 0.9995

Floor enforcement guarantees supply cannot be burned below the configured minimum.

### 15.3 Presale Distribution and Liquidity Model

The presale contract uses time-bounded phases with independent rate, cap, and bonus settings.

Core mechanics:
- Minimum purchase threshold (USDT-denominated)
- Per-phase hard cap enforcement
- Optional bonus basis points per phase
- Event-rich purchase logging for explorer-level traceability

Auto-liquidity behavior:
- A configured share of each purchase can be reserved for OBX/USDT liquidity
- When reserve threshold is reached, liquidity add is attempted through router integration
- LP result and liquidity actions are emitted as on-chain events

This model enables transparent distribution while supporting market depth operations through programmable liquidity policies.

### 15.4 Staking Contract Model

Staking is pool-based and supports configurable lock durations, minimum amounts, and APY.

Core mechanics:
- Burn on stake (optional, capped by contract safeguards)
- Burn on unstake (optional, capped by contract safeguards)
- Reward reserve funding by admin wallet
- Reward calculation proportional to elapsed lock time and APY basis points

Reward formula:
- reward = net_staked_amount x apy_bps x elapsed_time / (365_days x 10,000)

This approach avoids implicit minting and keeps reward distribution tied to explicit reserve funding.

### 15.5 Airdrop Contract Model

The airdrop campaign is designed around daily participation with delayed unlock.

Lifecycle:
1. Campaign is created with start, end, and daily claim amount.
2. Users claim once per UTC day during active campaign period.
3. Claimed OBX accumulates in locked user balance inside contract state.
4. After campaign end, unlock fee is revealed.
5. User pays unlock fee and receives full locked OBX in one transaction.

Safety controls:
- Reentrancy protection on claim/unlock flows
- Campaign duration bound
- Grace period before reclaim of unspent tokens
- Locked user balances protected from reclaim path

### 15.6 Wallet Architecture and Key Handling

Wallet operations combine user wallet records with EVM address history and controlled key handling.

Key characteristics:
- One primary OBX wallet context per user for core settlement
- Additional wallet generation subject to per-user limits
- Address generation through signer process (EVM-compatible wallet generation)
- Private keys encrypted at rest in application storage
- Private key fields excluded from standard model serialization

Design objective:
Maintain operational usability for user features while minimizing key exposure in application flows.

### 15.7 Payment Settlement and Post-Burn Accurate Crediting

For NOWPayments-driven buy flow, settlement follows a delivery-first policy:
1. Payment reaches terminal success state.
2. Backend resolves target EVM wallet from default OBX wallet context.
3. Backend submits OBX transfer transaction.
4. System credits internal OBX wallet based on delivered amount.

Delivered-amount logic:
- Primary method: parse ERC-20 Transfer logs in tx receipt for recipient amount.
- Secondary method: wallet balance delta around delivery execution.
- Fallback method: conservative burn-model estimate (requested x 0.9995).

Outcome:
Internal ledger credit aligns with deflationary token transfer reality.

### 15.8 Asynchronous Reliability (Background Finalization)

Settlement reliability does not depend on user session continuity.

Operational pattern:
- Pending NOWPayments orders are polled by scheduled background command.
- Finished orders are finalized (deliver + credit) even if user leaves payment page.
- Failed delivery cases are retained for retry flows and operational handling.

This architecture improves completion reliability and reduces user-side dependency on keeping browser tabs open.

### 15.9 Governance and Operational Controls

Administrative controls include:
- Pause controls on relevant contracts
- Two-step ownership transfer patterns in core contracts
- Configurable treasury/router/threshold parameters (where applicable)
- Chain/RPC and contract address configuration from managed settings

Governance recommendation:
Migrate production ownership and treasury authority to multi-signature control for stronger operational security.

### 15.10 Verifiability and Auditability

OBXCoin architecture is designed to expose key lifecycle actions on-chain:
- Transfer and burn traces
- Presale purchase events
- Liquidity events
- Staking and unstaking events
- Airdrop claim and unlock events

These traces support independent verification using public explorers and standard event indexing pipelines.

## 16) OBX Roadmap (2026-2027)

The following roadmap reflects planned utility expansion milestones:

| Target Date | Milestone |
|---|---|
| End of May 2026 | OBX Airdrops launch |
| End of July 2026 | OBX Staking launch |
| End of September 2026 | OBX Wallet V2 release |
| End of October 2026 | API integration for merchants |
| End of December 2026 | OBX Crypto Visa/Mastercard rollout |
| End of March 2027 | OBX Exchange launch |

Roadmap note:
- Timeline targets are strategic delivery goals and may be adjusted based on security, compliance, and infrastructure readiness.

---

Document metadata:
- Version: 2.3
- Last Updated: April 18, 2026
- Audience: OBX community, new users, and stakeholders
