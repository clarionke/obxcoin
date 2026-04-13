const express = require('express');
const router = express.Router();

const { checkSecurity } = require('../middleware/common/SecurityCheck');
const { CheckBalanceValidators, CheckBalanceValidatorHandler } = require('../Validator/GetBalanceValidator');
const tokenController = require('../Controllers/TokenController');
const trxController = require('../Controllers/TrxController');
const trcTokenController = require('../Controllers/TrcTokenController');

router.get('/health', (req, res) => {
    res.json({ status: true, message: 'ok', data: {} });
});

router.use(checkSecurity);

router.get('/', tokenController.getData);
router.post('/get-data', tokenController.getData);
router.post('/create-wallet', tokenController.generateAddress);
router.post('/check-wallet-balance', CheckBalanceValidators, CheckBalanceValidatorHandler, tokenController.getWalletBalance);
router.post('/check-estimate-gas', tokenController.checkEstimateGasFees);
router.post('/send-token', tokenController.sendToken);
router.post('/send-eth', tokenController.sendEth);
router.post('/get-transaction-data', tokenController.getDataByTransactionHash);
router.post('/get-transfer-event', tokenController.getLatestEvents);
router.post('/get-contract-details', tokenController.getContractDetails);
router.post('/get-address-by-pk', tokenController.getAddressByPk);

router.post('/read-contract-method', tokenController.readContractMethod);
router.post('/write-contract-method', tokenController.writeContractMethod);
router.post('/obx-contracts-overview', tokenController.getObxContractsOverview);

router.post('/get-trx-account', trxController.getTrxAccount);
router.post('/get-trx-address', trxController.getTrxAddressByPk);
router.post('/check-trx-address', trxController.checkTrxAddress);
router.post('/get-trx-confirmed-transaction', trxController.getTrxConfirmedTransaction);
router.post('/get-trc-transaction-event-watch', trcTokenController.getTrc20LatestEvent);
router.post('/check-gas', trcTokenController.getTrc20LatestEvent);

module.exports = router;
