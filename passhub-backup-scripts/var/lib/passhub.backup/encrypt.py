#!/usr/bin/python3

import sys
import os
from Crypto.PublicKey import RSA
from Crypto.Random import get_random_bytes
from Crypto.Cipher import AES, PKCS1_OAEP

pubkey_filename = os.path.dirname(__file__) + "/public.pem"

if len(sys.argv) >= 2:
    filename = sys.argv[1]
else:
    filename = input('Enter input filename: ')


if len(sys.argv) >= 3:
    output_filename = sys.argv[2]
else:
    output_filename = input('Enter output filename: ')

f = open(filename, "rb")
data = f.read()
f.close()

file_out = open(output_filename, "wb")

public_key = RSA.import_key(open(pubkey_filename).read())
session_key = get_random_bytes(16)

# Encrypt the session key with the public RSA key
cipher_rsa = PKCS1_OAEP.new(public_key)
enc_session_key = cipher_rsa.encrypt(session_key)

# Encrypt the data with the AES session key
cipher_aes = AES.new(session_key, AES.MODE_EAX)
ciphertext, tag = cipher_aes.encrypt_and_digest(data)
[ file_out.write(x) for x in (enc_session_key, cipher_aes.nonce, tag, ciphertext) ]
file_out.close()
