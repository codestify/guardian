#!/usr/bin/env node

const { execSync } = require("child_process");
const path = require("path");
const fs = require("fs");

// ANSI color codes for terminal output
const colors = {
    reset: "\x1b[0m",
    bright: "\x1b[1m",
    dim: "\x1b[2m",
    underscore: "\x1b[4m",
    blink: "\x1b[5m",
    reverse: "\x1b[7m",
    hidden: "\x1b[8m",

    fg: {
        black: "\x1b[30m",
        red: "\x1b[31m",
        green: "\x1b[32m",
        yellow: "\x1b[33m",
        blue: "\x1b[34m",
        magenta: "\x1b[35m",
        cyan: "\x1b[36m",
        white: "\x1b[37m",
    },

    bg: {
        black: "\x1b[40m",
        red: "\x1b[41m",
        green: "\x1b[42m",
        yellow: "\x1b[43m",
        blue: "\x1b[44m",
        magenta: "\x1b[45m",
        cyan: "\x1b[46m",
        white: "\x1b[47m",
    },
};

// Helper function to print colored output
function print(message, color = colors.reset) {
    console.log(`${color}${message}${colors.reset}`);
}

// Helper function to execute a command and print output
function runCommand(command, errorMessage) {
    try {
        print(`Running: ${command}`, colors.fg.cyan);
        execSync(command, { stdio: "inherit" });
        return true;
    } catch (error) {
        print(
            errorMessage || `Failed to execute command: ${command}`,
            colors.fg.red
        );
        if (error.stdout) console.log(error.stdout.toString());
        if (error.stderr) console.error(error.stderr.toString());
        return false;
    }
}

// Main build function
async function buildGuardian() {
    print("=== Building Guardian Package ===", colors.fg.green + colors.bright);

    // Clean dist directory if it exists
    print("🧹 Cleaning output directory...", colors.fg.yellow);
    if (fs.existsSync("dist")) {
        runCommand("npm run clean", "Failed to clean directory");
    } else {
        print("No dist directory found. Creating a new one.", colors.fg.blue);
    }

    // Install dependencies if node_modules doesn't exist
    if (!fs.existsSync("node_modules")) {
        print("📦 Installing dependencies...", colors.fg.yellow);
        if (!runCommand("npm install", "Failed to install dependencies")) {
            return false;
        }
    }

    // Build the package
    print("🔨 Building package...", colors.fg.yellow);
    if (!runCommand("npm run build", "Failed to build package")) {
        return false;
    }

    // Make sure examples directory exists
    if (!fs.existsSync("examples")) {
        print("📋 Creating examples directory...", colors.fg.blue);
        fs.mkdirSync("examples", { recursive: true });
    }

    print("✅ Build completed successfully!", colors.fg.green + colors.bright);

    // Check if dist directory now exists and has files
    if (fs.existsSync("dist")) {
        const files = fs.readdirSync("dist");
        if (files.length > 0) {
            print("📂 Generated files:", colors.fg.blue);
            files.forEach((file) => {
                const stats = fs.statSync(path.join("dist", file));
                const fileSizeKB = (stats.size / 1024).toFixed(2);
                print(`   - ${file} (${fileSizeKB} KB)`, colors.fg.white);
            });
        }
    }

    print("\n📝 Usage Example:", colors.fg.blue);
    print('  <script src="dist/guardian.js"></script>', colors.fg.white);
    print("  <script>", colors.fg.white);
    print("    const guardian = new Guardian();", colors.fg.white);
    print("    guardian.init();", colors.fg.white);
    print("  </script>", colors.fg.white);

    print("\n🌐 View the example at:", colors.fg.blue);
    print("  examples/basic-usage.html", colors.fg.white);

    return true;
}

// Run the build process
buildGuardian().catch((error) => {
    print(`Error: ${error.message}`, colors.fg.red);
    process.exit(1);
});
