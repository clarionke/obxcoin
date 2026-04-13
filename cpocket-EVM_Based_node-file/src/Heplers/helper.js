const TronWeb =  require('tronweb');
const Web3 = require("web3");


function contract_decimals($input = null)
{
    $output = {
        6 : "picoether",
        8 : "customeight",
        9 : 'nanoether',
        12 : 'microether',
        15 : 'milliether',
        18 : 'ether',
        21 : 'kether',
        24 : 'mether',
        27 : 'gether',
        30 : 'tether',
    };
    if (($input == null)) {
        return $output;
    } else {
        $result = 'ether';
        if (($output[$input])) {
            $result = $output[$input];
        }
        return $result;
    }
}

function customDecimal(input)
{
    let k='';
    for(j = 1; j <= input; j++) {
         k = k + '0';
    }
    return 1+k;
}

function customFromWei(amount,decimal)
{
    return (amount/powerOfTen(decimal)).toString()
}
function customToWei(amount,decimal)
{
    let input = String(amount ?? '0').trim();
    if (/^-?\d+(\.\d+)?e[+-]?\d+$/i.test(input)) {
        input = expandScientific(input);
    }

    if (!/^-?\d+(\.\d+)?$/.test(input)) {
        throw new Error('Invalid numeric amount');
    }

    const isNegative = input.startsWith('-');
    const unsigned = isNegative ? input.slice(1) : input;
    const parts = unsigned.split('.');
    const wholePart = parts[0] || '0';
    const fractionPart = parts[1] || '';
    const precision = Math.max(0, parseInt(decimal, 10) || 0);

    const paddedFraction = (fractionPart + '0'.repeat(precision)).slice(0, precision);
    const normalizedWhole = wholePart.replace(/^0+(?=\d)/, '');
    const combined = (normalizedWhole + paddedFraction).replace(/^0+(?=\d)/, '') || '0';

    return isNegative && combined !== '0' ? `-${combined}` : combined;
}

function expandScientific(value)
{
    let source = String(value).toLowerCase().trim();
    let sign = '';
    if (source.startsWith('-')) {
        sign = '-';
        source = source.slice(1);
    } else if (source.startsWith('+')) {
        source = source.slice(1);
    }

    const parts = source.split('e');
    if (parts.length !== 2) {
        return sign + source;
    }

    const coefficient = parts[0];
    const exponent = parseInt(parts[1], 10);
    if (!Number.isFinite(exponent)) {
        throw new Error('Invalid numeric amount');
    }

    const coeffParts = coefficient.split('.');
    const intPart = coeffParts[0] || '0';
    const fracPart = coeffParts[1] || '';
    const digits = (intPart + fracPart).replace(/^0+(?=\d)/, '') || '0';
    const dotIndex = intPart.length;
    const newDotIndex = dotIndex + exponent;

    let plain;
    if (newDotIndex <= 0) {
        plain = '0.' + '0'.repeat(Math.abs(newDotIndex)) + digits;
    } else if (newDotIndex >= digits.length) {
        plain = digits + '0'.repeat(newDotIndex - digits.length);
    } else {
        plain = digits.slice(0, newDotIndex) + '.' + digits.slice(newDotIndex);
    }

    plain = plain.replace(/^0+(?=\d)/, '');
    return sign + plain;
}
function powerOfTen(x) {
  return Math.pow(10, x);
}

function tronWebCall(req, res) {
    const tronWeb = new TronWeb ({
            fullHost: req.headers.chainlinks,
            headers: {
                "TRON-PRO-API-KEY": process.env.TRONGRID_API_KEY
            }
        });
    return tronWeb;
}
async function checkTx(tronWeb,txId) {
    return true;
  let txObj = await fetchTx(tronWeb, txId);
  if(txObj.hasOwnProperty('Error')) throw Error(txObj.Error);
    while(!txObj.hasOwnProperty('receipt')) {
      await new Promise(resolve => setTimeout(resolve, 45000)); //sleep in miliseconds
      txObj = await fetchTx(txId);
    }
  if(txObj.receipt.result == 'SUCCESS') return true;
  else return false;
}

async function fetchTx(tronWeb,txId) {
  return await tronWeb.trx.getTransactionInfo(txId);
}
async function gasLimit(network)
{
    const web3 = new Web3(network);
    const latestBlock = await web3.eth.getBlock('latest');
    // console.log(latestBlock)
    let blockGasUsed = latestBlock.gasUsed;
    blockGasUsed = 100000;
    return blockGasUsed;
}

module.exports = {
    tronWebCall,
    contract_decimals,
    customDecimal,
    customFromWei,
    customToWei,
    powerOfTen,
    checkTx,
    gasLimit
}