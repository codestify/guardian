import resolve from "@rollup/plugin-node-resolve";
import commonjs from "@rollup/plugin-commonjs";
import typescript from "@rollup/plugin-typescript";
import babel from "@rollup/plugin-babel";
import terser from "@rollup/plugin-terser";
import dts from "rollup-plugin-dts";
import { readFileSync } from "fs";
import { fileURLToPath } from "url";
import path from "path";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pkg = JSON.parse(
    readFileSync(path.resolve(__dirname, "./package.json"), "utf8")
);

// Main configuration for bundled outputs
const config = [
    // UMD build (for browsers)
    {
        input: "./resources/js/guardian.ts",
        output: {
            name: "Guardian",
            file: pkg.main,
            format: "umd",
            exports: "named",
            sourcemap: true,
        },
        plugins: [
            resolve(),
            commonjs(),
            typescript({ tsconfig: "./tsconfig.json" }),
            babel({
                babelHelpers: "bundled",
                exclude: "node_modules/**",
                presets: ["@babel/preset-env", "@babel/preset-typescript"],
            }),
        ],
    },

    // ESM build (for modern bundlers)
    {
        input: "./resources/js/guardian.ts",
        output: {
            file: pkg.module,
            format: "es",
            exports: "named",
            sourcemap: true,
        },
        plugins: [
            resolve(),
            commonjs(),
            typescript({ tsconfig: "./tsconfig.json" }),
        ],
    },

    // Minified UMD build
    {
        input: "./resources/js/guardian.ts",
        output: {
            name: "Guardian",
            file: "dist/guardian.min.js",
            format: "umd",
            exports: "named",
            sourcemap: true,
        },
        plugins: [
            resolve(),
            commonjs(),
            typescript({ tsconfig: "./tsconfig.json" }),
            babel({
                babelHelpers: "bundled",
                exclude: "node_modules/**",
                presets: ["@babel/preset-env", "@babel/preset-typescript"],
            }),
            terser(),
        ],
    },

    // TypeScript declaration files
    {
        input: "./resources/js/guardian.ts",
        output: {
            file: "dist/guardian.d.ts",
            format: "es",
        },
        plugins: [dts()],
    },
];

export default config;
