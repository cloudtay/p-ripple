import socket
import threading


def create_connection():
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.settimeout(30)
    try:
        s.connect(('127.0.0.1', 8008))
        for _ in range(100):
            s.send(b'------------------------'
                   b'------------------------'
                   b'------------------------'
                   b'------------------------'
                   b'------------------------'
                   b'------------------------'
                   b'------------------------'
                   b'------------------------'
                   b'------------------------'
                   b'------------------------')
            result = s.recv(1024)
            print(result)
    except socket.timeout:
        print("connect time out")
    finally:
        s.close()


for _ in range(200):
    threading.Thread(target=create_connection).start()
