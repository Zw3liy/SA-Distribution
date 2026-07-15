import os

# Base path for project
BASE_PATH = r"C:\Users\HP\OneDrive\Desktop\Zwelithini\SA Distribution"

folders = [
    "assets/css",
    "assets/js",
    "assets/images",
    "assets/icons",
    "assets/uploads",
    "components",
    "includes",
    "api",
    "admin",
    "products",
    "categories",
    "database"
]

def create_structure():
    for folder in folders:
        path = os.path.join(BASE_PATH, folder)
        os.makedirs(path, exist_ok=True)
        print(f"[OK] Created: {path}")

    # Create base files
    base_files = {
        "index.php": "<?php\n// Homepage entry point\n?>",
        "assets/css/styles.css": "/* Tailwind + custom overrides */",
        "assets/js/main.js": "// Modular JS entry point",
        "includes/init.php": "<?php\n// DB connection + session start\n?>"
    }

    for rel_path, content in base_files.items():
        path = os.path.join(BASE_PATH, rel_path)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"[OK] Created file: {path}")

if __name__ == "__main__":
    create_structure()
    print("\n✅ Project structure initialized successfully.")
