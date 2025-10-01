import telebot
import time
import threading 
import concurrent.futures 
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, NoSuchElementException, ElementClickInterceptedException

# =================================================================
# 1. KONFIGURASI BOT & SCHEDULING
# =================================================================

# Token Bot Telegram Anda (WAJIB DIISI)
TOKEN = "8329089699:AAHGlLYJuNJOI2SD4sPpWuBZp7F8yFrkUv0" 
bot = telebot.TeleBot(TOKEN)
TRUST_POSITIF_URL = "https://trustpositif.komdigi.go.id/"

# Global variables untuk manajemen thread pengulangan
list_check_thread = None
list_check_stop_event = threading.Event()
CHECK_INTERVAL_SECONDS = 400 # Jeda antar siklus (6 menit 40 detik)

# Selektor DOM berdasarkan inspeksi elemen
MODAL_TRIGGER_SELECTOR = (By.ID, "press-to-modal") 
INPUT_AREA_SELECTOR = (By.ID, "input-data") 
SUBMIT_BUTTON_SELECTOR = (By.ID, "text-footer1") 
RESULT_TABLE_SELECTOR = (By.ID, "daftar-block") 

# =================================================================
# 2. FUNGSI PEMBANTU
# =================================================================

def chunk_list(input_list, chunk_size):
    """Membagi list menjadi batch-batch kecil (chunks) dengan ukuran tertentu."""
    for i in range(0, len(input_list), chunk_size):
        yield input_list[i:i + chunk_size]

def format_summary(all_results):
    """Menghitung dan memformat ringkasan status dari semua hasil yang dicek."""
    count_aman = 0
    count_blocked = 0
    count_error = 0
    
    for result in all_results:
        # Cek berdasarkan string status yang sudah distandardisasi
        if "✅ AMAN" in result:
            count_aman += 1
        elif "🔴 DIBLOKIR" in result:
            count_blocked += 1
        elif "❌ ERROR" in result or "❓ TIDAK DIKETAHUI" in result:
            count_error += 1
            
    total_domains = len(all_results)
    
    summary_message = f"✈️ TOTAL {total_domains} DOMAIN ✈️\n"
    summary_message += f"✅ AMAN: {count_aman}\n"
    summary_message += f"🚫 BLOCKED: {count_blocked}\n"
    summary_message += f"🐛 ERROR: {count_error}"
    
    return summary_message

# =================================================================
# 3. FUNGSI CEK LINK (SCRAPING DENGAN SELENIUM)
# =================================================================

def cek_trustpositif(domains_list):
    """Melakukan pengecekan batch (maksimal 5) di situs TrustPositif."""
    
    # Konfigurasi Headless (Mode Cepat & Stabil)
    chrome_options = Options()
    chrome_options.add_argument("--headless") 
    chrome_options.add_argument("--no-sandbox")
    chrome_options.add_argument("--disable-dev-shm-usage")
    chrome_options.add_argument("--window-size=1920,1080")
    chrome_options.add_argument("user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36")
    
    driver = None
    results = []
    
    try:
        driver = webdriver.Chrome(options=chrome_options) 
        driver.get(TRUST_POSITIF_URL)
        
        # 1. KLIK Tombol Pemicu Modal (Menggunakan JavaScript Executor)
        modal_trigger = WebDriverWait(driver, 15).until(
            EC.presence_of_element_located(MODAL_TRIGGER_SELECTOR)
        )
        driver.execute_script("arguments[0].click();", modal_trigger)
        time.sleep(1)

        # 2. INPUT Domain ke Textarea
        input_area = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located(INPUT_AREA_SELECTOR)
        )
        input_area.clear()
        input_area.send_keys("\n".join(domains_list))
        
        # 3. KLIK Tombol Cari
        driver.find_element(*SUBMIT_BUTTON_SELECTOR).click()
        
        # 4. AMBIL HASIL DARI TABEL
        # Ini adalah bagian yang krusial untuk stabil
        WebDriverWait(driver, 20).until(
            EC.presence_of_element_located((By.CSS_SELECTOR, "#daftar-block tbody tr"))
        )
        
        rows = driver.find_elements(By.CSS_SELECTOR, "#daftar-block tbody tr")
        
        for row in rows:
            cells = row.find_elements(By.TAG_NAME, "td")
            
            if len(cells) >= 2:
                domain = cells[0].text.strip()
                status_mentah = cells[1].text.strip().lower()
                status_mentah = status_mentah.replace("\n", " ").replace("\xa0", " ").strip()
                
                # Konversi Status
                if status_mentah == "ada":
                    status_konversi = "🔴 DIBLOKIR"
                elif status_mentah == "tidak ada":
                    status_konversi = "✅ AMAN (Tidak Diblokir)"
                else:
                    status_konversi = f"❓ TIDAK DIKETAHUI ({status_mentah})"
                
                # Format output yang digunakan untuk parsing di handler
                results.append(f"`{domain}`: **{status_konversi}**")
        
        return results

    except Exception as e:
        # Jika terjadi error atau timeout (gagal menemukan tabel)
        error_message = f"❌ ERROR Fatal: Gagal memproses situs ({type(e).__name__})"
        for d in domains_list:
             results.append(f"`{d}`: **{error_message}**")
        return results

    finally:
        if driver:
            driver.quit() 

# =================================================================
# 4. FUNGSI THREADING UNTUK SIKLUS BERULANG
# =================================================================

def _periodic_check_task(chat_id, domains_list, stop_event):
    """Fungsi yang akan dijalankan di thread terpisah untuk pengecekan berulang."""
    while not stop_event.is_set():
        
        # 1. Inisialisasi dan Batching
        total_domains = len(domains_list)
        domain_batches = list(chunk_list(domains_list, 5))
        total_batches = len(domain_batches)
        all_accumulated_results = []
        
        # 2. Parallel Execution (Dibatasi 3 worker untuk stabilitas)
        MAX_SAFE_WORKERS = 3 
        MAX_WORKERS = min(total_batches, MAX_SAFE_WORKERS) 
        
        try:
            with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
                futures = [executor.submit(cek_trustpositif, batch) for batch in domain_batches]
                
                for future in concurrent.futures.as_completed(futures):
                    results_from_batch = future.result()
                    all_accumulated_results.extend(results_from_batch)

            # 3. Kirim Hasil Akhir
            
            # 3a. Kirim Ringkasan Total
            summary_message = format_summary(all_accumulated_results)
            bot.send_message(chat_id, summary_message) 
            
            # 3b. Kirim Pesan Terpisah untuk Domain yang Diblokir (3 KALI)
            blocked_domains = []
            for result in all_accumulated_results:
                if "🔴 DIBLOKIR" in result:
                    # Ambil domain dari string hasil
                    domain = result.split('`')[1] 
                    blocked_domains.append(f"📌 : {domain} 🚫 Blocked")
            
            # Logika pengiriman 3 kali
            for msg in blocked_domains:
                 for _ in range(3): # Mengirim pesan yang sama sebanyak 3 kali
                     bot.send_message(chat_id, msg)
            


        except Exception as e:
            bot.send_message(chat_id, f"⚠️ ERROR Kritis dalam siklus: {type(e).__name__}. Akan mencoba lagi setelah jeda.", parse_mode='Markdown')
            print(f"ERROR Kritis dalam siklus: {e}")

        # 4. Jeda untuk Siklus Berikutnya
        stop_event.wait(CHECK_INTERVAL_SECONDS)
    
# =================================================================
# 5. HANDLER PERINTAH TELEGRAM
# =================================================================

@bot.message_handler(commands=['cek'])
def handle_single_check(message):
    """Menangani perintah /cek {domain} dengan format output ringkas dan membersihkan output tambahan."""
    
    text_input = message.text.replace("/cek", "", 1).strip()
    domain_to_check = text_input.split()[0].strip() if text_input else None

    if not domain_to_check:
        bot.reply_to(message, "⚠️ Format salah. Mohon gunakan format: `/cek namadomain.tld`", parse_mode='Markdown')
        return

    # Pesan Progres (Sesuai Foto)
    # Gunakan URL penuh untuk link yang bisa diklik di pesan progres
    domain_url = domain_to_check if domain_to_check.startswith(('http', 'https')) else f"http://{domain_to_check}"

    bot.reply_to(message, 
                 f"⏳ Memulai pengecekan manual untuk:\n{domain_url}...", 
                 parse_mode='Markdown')
    
    single_batch = [domain_to_check]
    results_list = cek_trustpositif(single_batch) 
    
    if results_list:
        raw_result = results_list[0].replace('`', '').replace('**', '')

        # Konversi Status ke Bahasa Indonesia sederhana dan format ringkas (Sesuai Foto)
        if "AMAN" in raw_result:
             final_message = f"✅ {domain_to_check} : Tidak terblokir"
        elif "DIBLOKIR" in raw_result:
             final_message = f"🚫 {domain_to_check} : Terblokir"
        else:
             final_message = raw_result
        
        # Kirim Pesan Hasil Akhir (Hanya status, tidak ada output tambahan)
        bot.send_message(message.chat.id, final_message) # Menggunakan send_message biasa

    else:
        bot.send_message(message.chat.id, "❌ Gagal mendapatkan hasil. Coba lagi.")


@bot.message_handler(commands=['list'])
def handle_list_check(message):
    """Menangani perintah /list: Memulai atau mengganti siklus pengecekan berulang."""
    global list_check_thread, list_check_stop_event
    chat_id = message.chat.id
    
    text_input = message.text.replace("/list", "", 1).strip()
    raw_domains = text_input.split('\n')
    domains_to_check = [domain.strip() for domain in raw_domains if domain.strip() != ""]

    if not domains_to_check:
        bot.reply_to(message, "⚠️ Format salah. Gunakan `/list` diikuti domain baru di setiap baris.", parse_mode='Markdown')
        return

    total_domains = len(domains_to_check)

    # 1. Hentikan Loop yang Lama (jika ada)
    if list_check_thread and list_check_thread.is_alive():
        list_check_stop_event.set()
        list_check_thread.join(timeout=5)
        
    # 2. Reset Event dan Mulai Thread Baru
    list_check_stop_event.clear()
    
    list_check_thread = threading.Thread(
        target=_periodic_check_task, 
        args=(chat_id, domains_to_check, list_check_stop_event)
    )
    list_check_thread.daemon = True 
    list_check_thread.start()

    bot.reply_to(message, 
        f"✅ Pengecekan **{total_domains}** Domain.", 
        parse_mode='Markdown'
    )

@bot.message_handler(commands=['stoplist'])
def handle_stop_list(message):
    """Menghentikan siklus pengecekan berulang."""
    global list_check_thread, list_check_stop_event
    
    if list_check_thread and list_check_thread.is_alive():
        list_check_stop_event.set()
        list_check_thread.join(timeout=5)
        list_check_thread = None
        bot.reply_to(message, "🛑 **Pengecekan berulang** telah berhasil dihentikan.", parse_mode='Markdown')
    else:
        bot.reply_to(message, "ℹ️ Saat ini tidak ada pengecekan berulang yang aktif.", parse_mode='Markdown')

# =================================================================
# 6. START ROBOT LISTENER 24/7
# =================================================================

print("🤖 Bot Pengecek TrustPositif AKTIF! Menunggu perintah Telegram...")
try:
    bot.polling(none_stop=True) 
except Exception as e:
    print(f"❌ ERROR Fatal saat Bot Polling: {e}")