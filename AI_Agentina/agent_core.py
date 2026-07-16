import os
import anthropic
import openai

# Load API keys
api_key = os.getenv("ANTHROPIC_API_KEY")
client = anthropic.Anthropic(api_key=api_key)
openai.api_key = os.getenv("OPENAI_API_KEY")

def ask_claude(prompt):
    response = client.messages.create(
        model="claude-3-opus-20240229",
        max_tokens=500,
        messages=[{"role": "user", "content": prompt}]
    )
    return response.content[0].text

def ask_openai(prompt):
    response = openai.ChatCompletion.create(
        model="gpt-4",
        messages=[{"role": "user", "content": prompt}]
    )
    return response["choices"][0]["message"]["content"]

def run_agent(backend="claude", plugins=None):
    print("General-Purpose CLI Agent. Type 'exit' to quit.")
    while True:
        user_input = input("You: ")
        if user_input.lower() == "exit":
            break

        # Plugin commands
        if plugins and user_input.startswith(":"):
            command = user_input[1:].split()[0]
            if command in plugins:
                print("Plugin:", plugins[command](user_input))
                continue

        # Default AI response
        if backend == "openai":
            print("Agent:", ask_openai(user_input))
        else:
            print("Agent:", ask_claude(user_input))
