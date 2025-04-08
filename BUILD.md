# Guardian Build System

This document explains the build system and configuration for the Guardian JavaScript module.

## Overview

The Guardian build system transforms TypeScript source code into optimized JavaScript distributions in multiple formats (UMD, ESM) with proper TypeScript type definitions. The build process is configured to produce both minified and non-minified versions for production and debugging purposes.

## Build Outputs

When the build process completes, it generates the following files in the `dist/` directory:

-   `guardian.js` - Minified UMD build (Universal Module Definition) for direct browser use
-   `guardian.esm.js` - Minified ESM build (ES Modules) for use with modern bundlers
-   `guardian.umd.js` - Non-minified UMD build for debugging purposes
-   `guardian.d.ts` - TypeScript type definitions

## Build Tools

The build system uses the following tools:

-   **Rollup**: Main bundler for JavaScript modules
-   **TypeScript**: For type checking and transpilation
-   **Babel**: For additional transpilation features
-   **Terser**: For minification

## Build Commands

Here are the available build commands defined in `package.json`:

-   `npm run build`: Builds the library using Rollup
-   `npm run build:all`: Runs the comprehensive build script (build.js)
-   `npm run dev`: Builds with watch mode for development
-   `npm run test`: Runs the test suite
-   `npm run clean`: Removes the dist directory

## Quick Start

To build the library from scratch:

```bash
# Install dependencies and build the library in one step
./install.sh

# Or alternatively
npm run build:all
```

## Manual Build Process

If you prefer to run the steps manually:

```bash
# Install dependencies
npm install

# Clean the dist directory
npm run clean

# Build the library
npm run build

# Run tests
npm test
```

## Build Configuration Files

The build process is configured through the following files:

-   `rollup.config.js`: Configures Rollup bundling
-   `tsconfig.json`: TypeScript compiler configuration
-   `.babelrc`: Babel transpilation settings

## Rollup Configuration Details

The Rollup configuration (`rollup.config.js`) defines how the source files are bundled:

1. It takes the main `resources/js/guardian.ts` file as input
2. It applies TypeScript and Babel transformations
3. It outputs multiple formats (UMD, ESM)
4. It generates TypeScript type definitions

## TypeScript Configuration Details

The TypeScript configuration (`tsconfig.json`) defines how TypeScript files are processed:

1. Target is ES2015 for broad browser compatibility
2. Strong type checking is enabled
3. Source maps are generated for debugging
4. Declaration files are generated for TypeScript users

## Publishing

To publish the package to NPM:

```bash
# Ensure you are logged in to NPM
npm login

# Publish the package
npm publish
```

The `.npmignore` file ensures that only the necessary files are included in the published package.

## Browser Usage

After building, you can use the library in a browser:

```html
<!-- UMD (Global variable) -->
<script src="dist/guardian.js"></script>
<script>
    const guardian = new Guardian();
    guardian.init();
</script>
```

## Module Usage

For use with module bundlers:

```javascript
// ES Modules
import Guardian from "@codemystify/guardian";

const guardian = new Guardian({
    endpoint: "/api/report-bot",
    debug: true,
});
guardian.init();
```

## Example

A working example is provided in the `examples/basic-usage.html` file.
