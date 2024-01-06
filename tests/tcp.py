import asyncio
import time


async def tcp_echo_client(message, duration, count_dict, client_id):
    start_time = time.time()
    reader, writer = await asyncio.open_connection('127.0.0.1', 8007)
    while time.time() - start_time < duration:
        writer.write(message.encode())
        await writer.drain()
        await reader.read(100)
        count_dict[client_id] += 1
    writer.close()
    await writer.wait_closed()


async def main():
    duration = 10
    count_dict = {i: 0 for i in range(200)}
    tasks = [tcp_echo_client('Hello World', duration, count_dict, i) for i in range(200)]
    await asyncio.gather(*tasks)
    total_messages = sum(count_dict.values())
    print(f"Total messages sent and received: {total_messages}")


def call_user_func():
    asyncio.run(main())


callUserFunc()
