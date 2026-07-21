from dotenv import load_dotenv
import os
import sys

import anthropic
import openai
import ollama

# ============================================================
# Load Environment Variables
# ============================================================

load_dotenv(override=True)

AI_PROVIDER = os.getenv("AI_PROVIDER", "ollama").lower()

OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "llama3.2")
ANTHROPIC_MODEL = os.getenv("ANTHROPIC_MODEL", "claude-sonnet-4-6")
OPENAI_MODEL = os.getenv("OPENAI_MODEL", "gpt-4.1")

ANTHROPIC_KEY = os.getenv("ANTHROPIC_API_KEY")
OPENAI_KEY = os.getenv("OPENAI_API_KEY")

# ============================================================
# Startup
# ============================================================

print("=" * 60)
print("AI_Agentina Enterprise CLI")
print("=" * 60)

print(f"Provider : {AI_PROVIDER}")

if AI_PROVIDER == "ollama":
    print(f"Model    : {OLLAMA_MODEL}")

elif AI_PROVIDER == "anthropic":
    print(f"Model    : {ANTHROPIC_MODEL}")

elif AI_PROVIDER == "openai":
    print(f"Model    : {OPENAI_MODEL}")

print("=" * 60)

# ============================================================
# Initialize Clients
# ============================================================

client_claude = None

if ANTHROPIC_KEY:

    try:

        client_claude = anthropic.Anthropic(
            api_key=ANTHROPIC_KEY.strip()
        )

    except Exception as e:

        print(e)

if OPENAI_KEY:

    openai.api_key = OPENAI_KEY.strip()

# ============================================================
# Claude
# ============================================================

def ask_claude(prompt):

    if client_claude is None:

        return "Claude client not configured."

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

        return "OpenAI API Key not configured."

    try:

        client = openai.OpenAI()

        response = client.chat.completions.create(

            model=OPENAI_MODEL,

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
# Ollama
# ============================================================

def ask_ollama(prompt):

    try:

        response = ollama.chat(

            model=OLLAMA_MODEL,

            messages=[
                {
                    "role": "user",
                    "content": prompt
                }
            ]

        )

        return response["message"]["content"]

    except Exception as e:

        return f"Ollama Error:\n{e}"


# ============================================================
# Plugins
# ============================================================

def help_plugin(arg):

    return """
Available Commands

:help
:provider
:system
"""


def provider_plugin(arg):

    if AI_PROVIDER == "ollama":

        model = OLLAMA_MODEL

    elif AI_PROVIDER == "anthropic":

        model = ANTHROPIC_MODEL

    elif AI_PROVIDER == "openai":

        model = OPENAI_MODEL

    else:

        model = "Unknown"

    return f"""
Current Provider

{AI_PROVIDER}

Current Model

{model}
"""


def system_plugin(arg):

    return f"""
Platform

{sys.platform}

Python

{sys.version}
"""


PLUGINS = {

    "help": help_plugin,

    "provider": provider_plugin,

    "system": system_plugin

}

# ============================================================
# Chat Router
# ============================================================

def ask(prompt):

    if AI_PROVIDER == "ollama":

        return ask_ollama(prompt)

    elif AI_PROVIDER == "anthropic":

        return ask_claude(prompt)

    elif AI_PROVIDER == "openai":

        return ask_openai(prompt)

    else:

        return f"Unknown provider '{AI_PROVIDER}'."


# ============================================================
# CLI
# ============================================================

def run():

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

                print("\nUnknown command.\n")

            continue

        print("\nThinking...\n")

        answer = ask(prompt)

        print(answer)

        print()


# ============================================================
# Main
# ============================================================

if __name__ == "__main__":

    run()