// Set up global Jest mocks and environment variables here

// Add any global setup needed for tests

// Ensure Jest knows that jest.fn() is available even if tests don't import jest directly
global.jest = global.jest || {};
global.jest.fn = global.jest.fn || (() => jest.fn());
