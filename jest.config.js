export default {
    preset: "ts-jest",
    testEnvironment: "jsdom",
    testMatch: ["**/resources/js/tests/**/*.spec.ts"],
    collectCoverage: true,
    collectCoverageFrom: ["resources/js/**/*.ts"],
    coverageDirectory: "coverage/js",
    moduleFileExtensions: ["ts", "tsx", "js", "jsx", "json", "node"],
    transform: {
        "^.+\\.tsx?$": [
            "ts-jest",
            {
                tsconfig: {
                    target: "es5",
                    lib: ["es6", "dom"],
                    module: "commonjs",
                    strict: true,
                    esModuleInterop: true,
                    skipLibCheck: true,
                    forceConsistentCasingInFileNames: true,
                },
            },
        ],
    },
    // Mock canvas module to avoid native dependency issues
    moduleNameMapper: {
        canvas: "<rootDir>/resources/js/tests/mocks/canvasMock.js",
    },
    setupFiles: ["<rootDir>/resources/js/tests/setup.js"],
};
