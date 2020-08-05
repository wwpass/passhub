// https://dev.to/al_khovansky/generating-2fa-one-time-passwords-in-js-using-web-crypto-api-1hfo

function padCounter(counter) {
  const buffer = new ArrayBuffer(8);
  const bView = new DataView(buffer);

  const byteString = '0'.repeat(64); // 8 bytes
  const bCounter = (byteString + counter.toString(2)).slice(-64);

  for (let byte = 0; byte < 64; byte += 8) {
    const byteValue = parseInt(bCounter.slice(byte, byte + 8), 2);
    bView.setUint8(byte / 8, byteValue);
  }
  return buffer;
}

function DT(HS) {
  // First we take the last byte of our generated HS and extract last 4 bits out of it.
  // This will be our _offset_, a number between 0 and 15.
  const offset = HS[19] & 0b1111;

  // Next we take 4 bytes out of the HS, starting at the offset
  const P = ((HS[offset] & 0x7f) << 24) | (HS[offset + 1] << 16) | (HS[offset + 2] << 8) | HS[offset + 3]

  // Finally, convert it into a binary string representation
  const pString = P.toString(2);

  return pString;
}

function truncate(uKey) {
  const Sbits = DT(uKey);
  const Snum = parseInt(Sbits, 2);
  return Snum;
}

async function generateHOTP(key, counter) {
  const counterArray = padCounter(counter);
  const HS = await window.crypto.subtle.sign(
    {
      name: 'HMAC',
      hash: { name: 'SHA-1' },
    },
    key,
    counterArray,
  );

//  const uKey = new Uint8Array(key);
//  const Snum = truncate(uKey);
  const HS8 = new Uint8Array(HS);
  const Snum = truncate(HS8);
  // Make sure we keep leading zeroes
  const padded = ('000000' + (Snum % (10 ** 6))).slice(-6);
  return padded;
}

function getTOTPcounter() {
  const stepWindow = 30 * 1000;
  const time = Date.now();
  return Math.floor(time / stepWindow);
}

async function getTOTP(key) {
  const counter = getTOTPcounter();
  const result = await generateHOTP(key, counter);
  return result;
}

export default getTOTP;
