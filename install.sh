#!/bin/bash

# Colors for terminal output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Setting up Guardian package ===${NC}"

# Install dependencies
echo -e "${YELLOW}Installing dependencies...${NC}"
npm install

# Build the package
echo -e "${YELLOW}Building the package...${NC}"
npm run build

echo -e "${GREEN}=== Setup complete! ===${NC}"
echo -e "You can find the built files in the ${YELLOW}dist/${NC} directory."
echo -e "To build again, run: ${YELLOW}npm run build${NC}"
