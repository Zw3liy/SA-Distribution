import os

# -----------------------------
# General-purpose plugin commands
# -----------------------------

def open_file(command):
    """
    Usage: :open filename.py
    Opens and returns the content of a file.
    """
    try:
        filename = command.split()[1]
        with open(filename, "r", encoding="utf-8") as f:
            return f.read()
    except Exception as e:
        return f"Error opening file: {e}"

def save_file(command):
    """
    Usage: :save filename.py "new content"
    Saves content into a file (overwrites existing).
    """
    try:
        parts = command.split(maxsplit=2)
        filename, content = parts[1], parts[2]
        with open(filename, "w", encoding="utf-8") as f:
            f.write(content)
        return f"Saved to {filename}"
    except Exception as e:
        return f"Error saving file: {e}"

def run_shell(command):
    """
    Usage: :shell dir   (Windows)
           :shell ls    (Linux/Mac)
    Executes a shell command and returns the output.
    """
    try:
        cmd = command.split(maxsplit=1)[1]
        return os.popen(cmd).read()
    except Exception as e:
        return f"Error running shell command: {e}"

def calc(command):
    """
    Usage: :calc 2+2*5
    Evaluates a math expression safely.
    """
    try