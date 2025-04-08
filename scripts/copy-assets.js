import fs from "fs-extra";
import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Define paths
const rootDir = path.resolve(__dirname, "..");
const jsDir = path.join(rootDir, "dist", "js");
const distDir = path.join(rootDir, "dist");

// Make sure the directory exists
fs.ensureDirSync(jsDir);

// Copy the minified and declaration files to the js directory
console.log("Copying JavaScript files to dist/js...");

// Check if files exist in dist/js already
if (fs.existsSync(path.join(distDir, "guardian.min.js"))) {
    fs.copySync(
        path.join(distDir, "guardian.min.js"),
        path.join(jsDir, "guardian.min.js")
    );
}

if (fs.existsSync(path.join(distDir, "guardian.d.ts"))) {
    fs.copySync(
        path.join(distDir, "guardian.d.ts"),
        path.join(jsDir, "guardian.d.ts")
    );
}

// Create a fallback copy in the resources directory if needed
const resourcesDir = path.join(rootDir, "resources", "js");
fs.ensureDirSync(resourcesDir);

if (fs.existsSync(path.join(jsDir, "guardian.js"))) {
    fs.copySync(
        path.join(jsDir, "guardian.js"),
        path.join(resourcesDir, "guardian.built.js")
    );
    console.log("Created fallback copy in resources/js directory");
}

console.log("Assets copied successfully!");
