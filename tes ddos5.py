import requests
from concurrent.futures import ThreadPoolExecutor
import sys

def access_site(thread_id, url, headers):
    try:
        response = requests.get(url, headers=headers, timeout=10)
        response.raise_for_status()
        print(f"[Thread {thread_id}] ✅ Sukses - Status: {response.status_code}")
    except requests.exceptions.RequestException as err:
        print(f"[Thread {thread_id}] ⚠️ Error: {err}")

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python main_script.py <url> <jumlah_thread>")
        sys.exit(1)

    url = sys.argv[1]
    jumlah_thread = int(sys.argv[2])
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36"
    }

    with ThreadPoolExecutor(max_workers=jumlah_thread) as executor:
        executor.map(lambda tid: access_site(tid, url, headers), range(1, jumlah_thread + 1))

    print("✅ Semua thread selesai.")
