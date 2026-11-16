import os
import sys
import time
import tracemalloc
import ctypes
from ctypes import wintypes

# Windows Process Memory info struct
class PROCESS_MEMORY_COUNTERS(ctypes.Structure):
    _fields_ = [
        ('cb', wintypes.DWORD),
        ('PageFaultCount', wintypes.DWORD),
        ('PeakWorkingSetSize', ctypes.c_size_t),
        ('WorkingSetSize', ctypes.c_size_t),
        ('QuotaPeakPagedPoolUsage', ctypes.c_size_t),
        ('QuotaPagedPoolUsage', ctypes.c_size_t),
        ('QuotaPeakNonPagedPoolUsage', ctypes.c_size_t),
        ('QuotaNonPagedPoolUsage', ctypes.c_size_t),
        ('PagefileUsage', ctypes.c_size_t),
        ('PeakPagefileUsage', ctypes.c_size_t),
    ]

def get_process_memory_mb():
    try:
        handle = ctypes.windll.kernel32.GetCurrentProcess()
        counters = PROCESS_MEMORY_COUNTERS()
        counters.cb = ctypes.sizeof(PROCESS_MEMORY_COUNTERS)
        if ctypes.windll.psapi.GetProcessMemoryInfo(handle, ctypes.byref(counters), counters.cb):
            return {
                'working_set_mb': counters.WorkingSetSize / 1024 / 1024,
                'peak_working_set_mb': counters.PeakWorkingSetSize / 1024 / 1024,
                'private_bytes_mb': counters.PagefileUsage / 1024 / 1024,
            }
    except Exception:
        pass
    return {'working_set_mb': 0, 'peak_working_set_mb': 0, 'private_bytes_mb': 0}

# Ensure ai_engine is in path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'ai_engine')))
os.environ['TREND_ENGINE_INTERNAL_TOKEN'] = 'benchmark-token'
os.environ['AI_ENGINE_INTERNAL_TOKEN'] = 'benchmark-token'

tracemalloc.start()
t0 = time.perf_counter()

from fastapi.testclient import TestClient
from ai_engine.main import app

mem_startup = get_process_memory_mb()
curr_traced, peak_traced = tracemalloc.get_traced_memory()
startup_time = (time.perf_counter() - t0) * 1000

print("--- 1. FASTAPI ENGINE STARTUP ---")
print(f"Working Set RAM: {mem_startup['working_set_mb']:.2f} MB")
print(f"Private Bytes (Commit): {mem_startup['private_bytes_mb']:.2f} MB")
print(f"Peak Working Set: {mem_startup['peak_working_set_mb']:.2f} MB")
print(f"Python Heap Allocations: {curr_traced / 1024 / 1024:.2f} MB (Peak: {peak_traced / 1024 / 1024:.2f} MB)")
print(f"Startup Duration: {startup_time:.2f} ms\n")

client = TestClient(app)

# 2. Health Request
t_req = time.perf_counter()
res = client.get("/health")
req_time = (time.perf_counter() - t_req) * 1000
mem_health = get_process_memory_mb()
print("--- 2. FASTAPI REQUEST (/health) ---")
print(f"Status: {res.status_code}")
print(f"Working Set RAM: {mem_health['working_set_mb']:.2f} MB")
print(f"Duration: {req_time:.2f} ms\n")

# 3. Content Strategy Generation Request
t_gen = time.perf_counter()
brand_payload = {
    "name": "SocialPilot Test",
    "industry": "Marketing & SaaS",
    "niche": "Social Media Growth",
    "audience": "Creators and Founders",
    "tone": "sharp and informative",
}
res_strat = client.post(
    "/internal/v1/content/strategy",
    json={"brand": brand_payload, "objective": "brand_authority", "platform": "linkedin"},
    headers={"Authorization": "Bearer benchmark-token"},
)
gen_time = (time.perf_counter() - t_gen) * 1000
mem_strat = get_process_memory_mb()
curr_traced, peak_traced = tracemalloc.get_traced_memory()

print("--- 3. FASTAPI AI REQUEST (/internal/v1/content/strategy) ---")
print(f"Status: {res_strat.status_code}")
print(f"Working Set RAM: {mem_strat['working_set_mb']:.2f} MB")
print(f"Peak Working Set: {mem_strat['peak_working_set_mb']:.2f} MB")
print(f"Peak Python Heap: {peak_traced / 1024 / 1024:.2f} MB")
print(f"Duration: {gen_time:.2f} ms\n")

# 4. Content Generation Request
t_full = time.perf_counter()
res_gen = client.post(
    "/internal/v1/content/generate",
    json={"brand": brand_payload, "platform": "linkedin", "include_variations": True, "variation_count": 3},
    headers={"Authorization": "Bearer benchmark-token"},
)
full_time = (time.perf_counter() - t_full) * 1000
mem_gen = get_process_memory_mb()
curr_traced, peak_traced = tracemalloc.get_traced_memory()

print("--- 4. FASTAPI FULL GENERATION (/internal/v1/content/generate) ---")
print(f"Status: {res_gen.status_code}")
print(f"Working Set RAM: {mem_gen['working_set_mb']:.2f} MB")
print(f"Peak Working Set: {mem_gen['peak_working_set_mb']:.2f} MB")
print(f"Peak Python Heap: {peak_traced / 1024 / 1024:.2f} MB")
print(f"Duration: {full_time:.2f} ms\n")

client.close()
