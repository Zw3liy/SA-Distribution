from dotenv import load_dotenv
import os

load_dotenv(override=True)

print("=" * 60)
print("Current directory:", os.getcwd())

key = os.getenv("ANTHROPIC_API_KEY")

if key:
    print("Key loaded successfully")
    
    print("Starts with:", key[:20])
    print("Ends with:", key[-10:])
    print("Length:", len(key))
else:
    print("No key found")

print("=" * 60)