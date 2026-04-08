/**
 * contracts/signer.js
 *
 * Signs and broadcasts EVM transactions for OBXPresale admin operations.
 * Called by BlockchainService::callSignerScript() with JSON payload via stdin.
 *
 * Requires: ethers v5 (npm install ethers@5)
 *
 * Environment:
 *   OWNER_PRIVATE_KEY  — admin BSC wallet private key (passed via env, never in logs)
 *
 * Usage: echo '{"action":"addPhase",...}' | node contracts/signer.js
 */

const { ethers } = require('ethers');

const ABI = [
    'function addPhase(string name, uint256 startTime, uint256 endTime, uint256 rateUsdt, uint256 tokenCap, uint256 bonusBps, uint256 dbPhaseId)',
    'function updatePhase(uint256 contractPhaseIndex, string name, uint256 startTime, uint256 endTime, uint256 rateUsdt, uint256 tokenCap, uint256 bonusBps, bool active)',
    'function setPhaseActive(uint256 contractPhaseIndex, bool active)',
];

async function main() {
    let raw = '';
    for await (const chunk of process.stdin) raw += chunk;

    let payload;
    try {
        payload = JSON.parse(raw);
    } catch (e) {
        process.stdout.write(JSON.stringify({ error: 'Invalid JSON payload' }));
        process.exit(1);
    }

    const privateKey = process.env.OWNER_PRIVATE_KEY;
    if (!privateKey) {
        process.stdout.write(JSON.stringify({ error: 'OWNER_PRIVATE_KEY not set' }));
        process.exit(1);
    }

    const provider = new ethers.providers.JsonRpcProvider(payload.rpcUrl);
    const wallet   = new ethers.Wallet(privateKey, provider);
    const contract = new ethers.Contract(payload.contractAddress, ABI, wallet);

    let tx;
    const p = payload.params;

    try {
        switch (payload.action) {
            case 'addPhase':
                tx = await contract.addPhase(
                    p.name,
                    p.startTime,
                    p.endTime,
                    ethers.BigNumber.from(p.rateUsdt),
                    ethers.BigNumber.from(p.tokenCap),
                    p.bonusBps,
                    p.dbPhaseId
                );
                break;

            case 'updatePhase':
                tx = await contract.updatePhase(
                    p.contractPhaseIndex,
                    p.name,
                    p.startTime,
                    p.endTime,
                    ethers.BigNumber.from(p.rateUsdt),
                    ethers.BigNumber.from(p.tokenCap),
                    p.bonusBps,
                    p.active
                );
                break;

            case 'setPhaseActive':
                tx = await contract.setPhaseActive(p.contractPhaseIndex, p.active);
                break;

            default:
                process.stdout.write(JSON.stringify({ error: 'Unknown action: ' + payload.action }));
                process.exit(1);
        }

        await tx.wait(1); // wait for 1 confirmation
        process.stdout.write(JSON.stringify({ txHash: tx.hash, success: true }));

    } catch (e) {
        process.stdout.write(JSON.stringify({ error: e.message }));
        process.exit(1);
    }
}

main().catch(e => {
    process.stdout.write(JSON.stringify({ error: e.message }));
    process.exit(1);
});
