from dotenv import load_dotenv
import os
import sys
import anthropic
import openai

# ============================================================
# Load Environment Variables
# ============================================================

load_dotenv(override=True)

AI_PROVIDER = os.getenv("AI_PROVIDER", "anthropic").lower()
ANTHROPIC_MODEL = os.getenv(
    "ANTHROPIC_MODEL",
    "claude-3-5-sonnet-latest"
)

ANTHROPIC_KEY = os.getenv("ANTHROPIC_API_KEY")
OPENAI_KEY = os.getenv("OPENAI_API_KEY")

print("=" * 60)

if ANTHROPIC_KEY:
    print("Key loaded successfully")
    print("Starts with :", repr(ANTHROPIC_KEY[:20]))
    print("Ends with   :", repr(ANTHROPIC_KEY[-10:]))
    print("Length      :", len(ANTHROPIC_KEY))
else:
    print("No key loaded")

print("=" * 60)

# ============================================================
# Display Startup Information
# ============================================================

print("=" * 60)
print("AI_Agentina Enterprise CLI")
print("=" * 60)
print(f"Provider : {AI_PROVIDER}")

if ANTHROPIC_KEY:
    print(f"Anthropic Key : Loaded ({len(ANTHROPIC_KEY)} characters)")
else:
    print("Anthropic Key : NOT FOUND")

if OPENAI_KEY:
    print("OpenAI Key    : Loaded")
else:
    print("OpenAI Key    : Not configured")

print("=" * 60)

# ============================================================
# Initialize AI Clients
# ============================================================

client_claude = None

if ANTHROPIC_KEY:
    try:
        client_claude = anthropic.Anthropic(
            api_key=ANTHROPIC_KEY.strip()
        )
    except Exception as e:
        print(f"Failed to initialize Claude client: {e}")

if OPENAI_KEY:
    openai.api_key = OPENAI_KEY.strip()

# ============================================================
# Claude
# ============================================================

def ask_claude(prompt):

    if client_claude is None:
        return "Claude client is not configured."

    try:

        response = client_claude.messages.create(

            model=ANTHROPIC_MODEL,

            max_tokens=1024,

            messages=[
                {
                    "role": "user",
                    "content": prompt
                }
            ]

        )

        return response.content[0].text

    except Exception as e:

        return f"Claude Error:\n{e}"

# ============================================================
# OpenAI
# ============================================================

def ask_openai(prompt):

    if not OPENAI_KEY:
        return "OpenAI API key not configured."

    try:

        client = openai.OpenAI()

        response = client.chat.completions.create(

            model="gpt-4.1",

            messages=[
                {
                    "role": "user",
                    "content": prompt
                }
            ]

        )

        return response.choices[0].message.content

    except Exception as e:

        return f"OpenAI Error:\n{e}"

# ============================================================
# Plugins
# ============================================================

def help_plugin(arg):

    return """
Available Commands

:help
:system
:provider
"""

def system_plugin(arg):

    return f"""
Platform : {sys.platform}

Python   : {sys.version}
"""

def provider_plugin(arg):

    return f"""
Current Provider

{AI_PROVIDER}

Current Model

{ANTHROPIC_MODEL}
"""

PLUGINS = {

    "help": help_plugin,

    "system": system_plugin,

    "provider": provider_plugin

}

# ============================================================
# Agent Loop
# ============================================================

def run_agent():

    print()
    print(f"AI Provider : {AI_PROVIDER}")
    print(f"Claude Model: {ANTHROPIC_MODEL}")
    print()
    print("Type exit to quit.")
    print("Type :help for commands.")
    print()

    while True:

        try:

            prompt = input("You: ").strip()

        except (KeyboardInterrupt, EOFError):

            print("\nGoodbye.")
            break

        if not prompt:

            continue

        if prompt.lower() == "exit":

            print("Goodbye.")

            break

        if prompt.startswith(":"):

            cmd = prompt[1:].split(maxsplit=1)

            name = cmd[0]

            arg = cmd[1] if len(cmd) > 1 else ""

            if name in PLUGINS:

                print()

                print(PLUGINS[name](arg))

                print()

            else:

                print("Unknown command.")

            continue

        print()

        print("Thinking...")

        if AI_PROVIDER == "anthropic":

            answer = ask_claude(prompt)

        elif AI_PROVIDER == "openai":

            answer = ask_openai(prompt)

        else:

            answer = f"Unknown provider '{AI_PROVIDER}'."

        print()

        print(answer)

        print()

# ============================================================
# Main
# ============================================================

if __name__ == "__main__":

    run_agent()
