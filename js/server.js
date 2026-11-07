const express = require('express');
const axios = require('axios');
const bodyParser = require('body-parser');
const path = require('path');
require('dotenv').config();

const app = express();
const allowedLocalOrigins = new Set([
  'http://localhost',
  'http://127.0.0.1',
  'http://localhost:8000',
  'http://127.0.0.1:8000'
]);

app.use((req, res, next) => {
  const origin = req.get('Origin');
  if (origin && allowedLocalOrigins.has(origin)) {
    res.set('Access-Control-Allow-Origin', origin);
    res.set('Vary', 'Origin');
    res.set('Access-Control-Allow-Methods', 'POST, OPTIONS');
    res.set('Access-Control-Allow-Headers', 'Content-Type');
  }
  if (req.method === 'OPTIONS') {
    return res.sendStatus(204);
  }
  next();
});
app.use(bodyParser.json());
app.use(express.static(path.join(__dirname)));

const consumerKey = process.env.MPESA_CONSUMER_KEY;
const consumerSecret = process.env.MPESA_CONSUMER_SECRET;
const shortcode = process.env.MPESA_SHORTCODE;
const passkey = process.env.MPESA_PASSKEY;
const callbackURL = process.env.MPESA_CALLBACK_URL;
const mpesaEnvironment = process.env.MPESA_ENVIRONMENT || 'sandbox';
const mpesaApiBase = mpesaEnvironment === 'production'
  ? 'https://api.safaricom.co.ke'
  : 'https://sandbox.safaricom.co.ke';

async function getAccessToken() {
  const auth = Buffer.from(`${consumerKey}:${consumerSecret}`).toString('base64');
  const response = await axios.get(
    `${mpesaApiBase}/oauth/v1/generate?grant_type=client_credentials`,
    { headers: { Authorization: `Basic ${auth}` }, timeout: 15000 }
  );
  if (!response.data.access_token) {
    throw new Error('Safaricom did not return an access token.');
  }
  return response.data.access_token;
}

app.post('/stkpush', async (req, res) => {
  if (!['sandbox', 'production'].includes(mpesaEnvironment)) {
    return res.status(500).json({ success: false, message: 'Invalid M-Pesa environment configuration.' });
  }
  if (!consumerKey || !consumerSecret || !shortcode || !passkey || !callbackURL) {
    return res.status(503).json({
      success: false,
      message: 'M-Pesa is not fully configured. Please contact the site administrator.'
    });
  }

  let callback;
  try {
    callback = new URL(callbackURL);
  } catch (error) {
    return res.status(503).json({
      success: false,
      message: 'The M-Pesa callback URL must be a publicly reachable HTTPS URL.'
    });
  }
  if (callback.protocol !== 'https:') {
    return res.status(503).json({
      success: false,
      message: 'The M-Pesa callback URL must be a publicly reachable HTTPS URL.'
    });
  }

  const body = req.body && typeof req.body === 'object' ? req.body : {};
  const phone = typeof body.phone === 'string' ? body.phone : '';
  const amount = Number(body.amount);
  const cause = typeof body.cause === 'string' ? body.cause.trim() : '';
  if (!/^254[17]\d{8}$/.test(phone)) {
    return res.status(400).json({ success: false, message: 'Enter a valid Kenyan mobile number.' });
  }
  if (!Number.isSafeInteger(amount) || amount < 1) {
    return res.status(400).json({ success: false, message: 'Enter a whole amount of at least KSh 1.' });
  }
  if (!cause || cause.length > 100) {
    return res.status(400).json({ success: false, message: 'Select a valid giving cause.' });
  }

  try {
    const token = await getAccessToken();
    const timestamp = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
    const password = Buffer.from(shortcode + passkey + timestamp).toString('base64');

    const stkRequest = {
      BusinessShortCode: shortcode,
      Password: password,
      Timestamp: timestamp,
      TransactionType: 'CustomerPayBillOnline',
      Amount: amount,
      PartyA: phone,
      PartyB: shortcode,
      PhoneNumber: phone,
      CallBackURL: callbackURL,
      AccountReference: cause.replace(/[^a-zA-Z0-9]/g, '').slice(0, 12) || 'Giving',
      TransactionDesc: cause.slice(0, 20)
    };

    const response = await axios.post(
      `${mpesaApiBase}/mpesa/stkpush/v1/processrequest`,
      stkRequest,
      { headers: { Authorization: `Bearer ${token}` } }
    );

    if (response.data.ResponseCode !== '0') {
      return res.status(502).json({
        success: false,
        message: response.data.ResponseDescription || 'Safaricom did not accept the payment request.'
      });
    }

    return res.json({
      success: true,
      message: 'STK Push request accepted.',
      data: {
        merchantRequestId: response.data.MerchantRequestID,
        checkoutRequestId: response.data.CheckoutRequestID,
        customerMessage: response.data.CustomerMessage
      }
    });
  } catch (error) {
    console.error(error.response?.data || error.message || error);
    const message = error.response?.data?.errorMessage
      || error.response?.data?.ResponseDescription
      || 'Unable to reach the M-Pesa service. Please try again later.';
    return res.status(502).json({ success: false, message });
  }
});

app.post('/dpo-pay', (req, res) => {
  console.log('DPO Pay request:', JSON.stringify(req.body, null, 2));
  res.json({ success: true, message: 'DPO Pay placeholder endpoint received the request.' });
});

app.post('/callback', (req, res) => {
  console.log('M-Pesa Callback:', JSON.stringify(req.body, null, 2));
  res.json({ message: 'Callback received' });
});

app.listen(3000, () => console.log('Server running on port 3000'));
