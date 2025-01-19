#!/usr/bin/python3

import sys


from Crypto.PublicKey import RSA
from Crypto.Cipher import AES, PKCS1_OAEP

if len(sys.argv) >= 2:
    filename = sys.argv[1]
else:
    filename = input('Enter encrypted file name: ')



if filename.endswith(".enc"):
    output_filename = filename[:-4]
else:
    output_filename = filename + ".decrypted"

with open(filename, "rb") as f:

    private_key = RSA.import_key(open("private.pem").read())

    enc_session_key, nonce, tag, ciphertext = \
       [ f.read(x) for x in (private_key.size_in_bytes(), 16, 16, -1) ]

    # Decrypt the session key with the private RSA key
    cipher_rsa = PKCS1_OAEP.new(private_key)
    session_key = cipher_rsa.decrypt(enc_session_key)

    # Decrypt the data with the AES session key
    cipher_aes = AES.new(session_key, AES.MODE_EAX, nonce)
    data = cipher_aes.decrypt_and_verify(ciphertext, tag)

    file_out = open(output_filename, "wb")
    file_out.write(data)


    