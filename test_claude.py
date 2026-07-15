import anthropic, os

client = anthropic.Anthropic(api_key=os.getenv("ANTHROPIC_API_KEY"))

response = client.messages.create(
    model="claude-3-opus-20240229",
    max_tokens=200,
    messages=[{"role": "user", "content": "Hello Claude, test message!"}]
)

print(response.content[0].text)
