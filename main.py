import os
import sys
import anthropic
import openai

# --- 1. Load and Verify API Keys ---
ANTHROPIC_KEY = os.getenv("ANTHROPIC_API_KEY")
OPENAI_KEY = os.getenv("OPENAI_API_KEY")

# Initialize clients only if keys are present to avoid startup crashes
client_claude = None
if ANTHROPIC_KEY:
    client_claude = anthropic.Anthropic(api_key=ANTHROPIC_KEY)
else:
    print("Warning: ANTHROPIC_API_KEY environment variable not found.")

if OPENAI_KEY:
    openai.api_key = OPENAI_KEY
else:
    print("Warning: OPENAI_API_KEY environment variable not found.")


# --- 2. AI Interaction Functions ---
def ask_claude(prompt):
    if not client_claude:
        return "Error: Claude client is not configured. Please set your ANTHROPIC_API_KEY."
    try:
        response = client_claude.messages.create(
            model="claude-3-opus-20240229",
            max_tokens=500,
            messages=[{"role": "user", "content": prompt}]
        )
        return response.content[0].text
    except Exception as e:
        return f"Error calling Claude: {e}"


def ask_openai(prompt):
    if not OPENAI_KEY:
        return "Error: OpenAI client is not configured. Please set your OPENAI_KEY."
    try:
        # Compatibility with openai>=1.0.0 (Recommended)
        client_openai = openai.OpenAI()
        response = client_openai.chat.completions.create(
            model="gpt-4",
            messages=[{"role": "user", "content": prompt}]
        )
        return response.choices[0].message.content
    except AttributeError:
        # Fallback for old openai versions (<1.0.0)
        try:
            response = openai.ChatCompletion.create(
                model="gpt-4",
                messages=[{"role": "user", "content": prompt}]
            )
            return response["choices"][0]["message"]["content"]
        except Exception as e:
            return f"Error calling OpenAI: {e}"
    except Exception as e:
        return f"Error calling OpenAI: {e}"


# --- 3. Example Plugins (To replace plugins_general.py syntax errors) ---
def help_plugin(arg):
    return "Available commands: :help, :system"

def system_plugin(arg):
    return f"Platform: {sys.platform} | Python: {sys.version.split()[0]}"

# Register your plugins here
DEFAULT_PLUGINS = {
    "help": help_plugin,
    "system": system_plugin
}


# --- 4. Main Agent Loop ---
def run_agent(backend="claude", plugins=None):
    if plugins is None:
        plugins = DEFAULT_PLUGINS

    print(f"=== General-Purpose CLI Agent (Backend: {backend}) ===")
    print("Type 'exit' to quit. Use ':' prefixes for plugin commands (e.g., ':help').\n")
    
    while True:
        try:
            user_input = input("You: ").strip()
        except (KeyboardInterrupt, EOFError):
            print("\nGoodbye!")
            break

        if not user_input:
            continue

        if user_input.lower() == "exit":
            print("Goodbye!")
            break

        # Check if input is a plugin command (starts with ':')
        if user_input.startswith(":"):
            parts = user_input[1:].split(maxsplit=1)
            command = parts[0]
            argument = parts[1] if len(parts) > 1 else ""
            
            if command in plugins:
                try:
                    result = plugins[command](argument)
                    print(f"Plugin [{command}]: {result}\n")
                except Exception as e:
                    print(f"Plugin Error [{command}]: {e}\n")
            else:
                print(f"Unknown plugin command: :{command}. Type :help for a list.\n")
            continue

        # Route to the selected AI backend
        print("Agent thinking...")
        if backend.lower() == "openai":
            response = ask_openai(user_input)
        else:
            response = ask_claude(user_input)
            
        print(f"Agent: {response}\n")


if __name__ == "__main__":
    # You can change backend to "openai" if preferred
    run_agent(backend="claude")