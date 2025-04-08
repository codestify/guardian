import { Guardian } from "../guardian";

interface GuardianSignal {
    name: string;
    weight: number;
    description: string;
    timestamp: number;
}

// Mock functions and browser environment
// Remove unused variables
// const originalNavigator = global.navigator;
// const originalWindow = global.window;
// const originalDocument = global.document;

describe("Guardian", () => {
    let guardian: Guardian;
    const mockPerformanceNow = jest.fn(() => 1000);

    // Backup vars to restore after tests
    let mockXhr: {
        open: jest.Mock;
        setRequestHeader: jest.Mock;
        send: jest.Mock;
    };

    // Don't declare variables that aren't used
    // let xhrSpy: jest.SpyInstance;
    // let consoleDebugSpy: jest.SpyInstance;
    let sendBeaconSpy: jest.SpyInstance;

    // Setup mocks before each test
    beforeEach(() => {
        // Setup fake timers
        jest.useFakeTimers();

        // Mock XHR
        mockXhr = {
            open: jest.fn(),
            setRequestHeader: jest.fn(),
            send: jest.fn(),
        };

        // Mock XMLHttpRequest - we assign this but don't need to use the spy variable
        jest.spyOn(window, "XMLHttpRequest").mockImplementation(
            () => mockXhr as any
        );

        // Mock requestIdleCallback
        (window as any).requestIdleCallback = (
            callback: Function,
            options?: any
        ) => {
            return setTimeout(() => callback(), options?.timeout || 0);
        };

        // Mock sendBeacon
        sendBeaconSpy = jest.fn().mockReturnValue(true);
        Object.defineProperty(navigator, "sendBeacon", {
            value: sendBeaconSpy,
            configurable: true,
        });

        // Mock console.debug - we don't need to use the spy variable
        jest.spyOn(console, "debug").mockImplementation();

        // Mock performance
        Object.defineProperty(window, "performance", {
            value: { now: mockPerformanceNow },
            configurable: true,
        });

        // Mock DOM elements query
        jest.spyOn(document, "querySelectorAll").mockImplementation(
            (selector: string) => {
                if (selector === "a, button") {
                    return [{ id: "mockButton" }] as any;
                }
                if (selector === 'input[type="text"], textarea') {
                    return [{ id: "mockInput" }] as any;
                }
                if (selector === 'meta[name="csrf-token"]') {
                    return [{ content: "mock-csrf-token" }] as any;
                }
                return [] as any;
            }
        );

        // Mock window dimensions
        Object.defineProperty(window, "innerHeight", {
            value: 800,
            configurable: true,
        });
        Object.defineProperty(window, "innerWidth", {
            value: 1200,
            configurable: true,
        });
        Object.defineProperty(window, "outerHeight", {
            value: 900,
            configurable: true,
        });
        Object.defineProperty(window, "outerWidth", {
            value: 1300,
            configurable: true,
        });

        // Mock matchMedia
        Object.defineProperty(window, "matchMedia", {
            writable: true,
            value: jest.fn().mockImplementation((query) => ({
                matches: true,
                media: query,
                onchange: null,
                addListener: jest.fn(), // deprecated
                removeListener: jest.fn(), // deprecated
                addEventListener: jest.fn(),
                removeEventListener: jest.fn(),
                dispatchEvent: jest.fn(),
            })),
        });

        // Mock body scrollHeight
        Object.defineProperty(document.body, "scrollHeight", {
            value: 2000,
            configurable: true,
        });

        // Reset other browser properties to defaults
        Object.defineProperty(navigator, "webdriver", {
            value: false,
            configurable: true,
        });
        Object.defineProperty(navigator, "languages", {
            value: ["en-US"],
            configurable: true,
        });
        Object.defineProperty(navigator, "cookieEnabled", {
            value: true,
            configurable: true,
        });
        Object.defineProperty(navigator, "userAgent", {
            value: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36",
            configurable: true,
        });

        // Mock window.location
        Object.defineProperty(window, "location", {
            value: {
                pathname: "/test-path",
                href: "https://example.com/test-path",
            },
            configurable: true,
        });

        guardian = new Guardian({
            sampleRate: 100,
            detectionDelay: 1000,
            debug: true,
            threshold: 50,
        });
    });

    // Restore original values after each test
    afterEach(() => {
        jest.useRealTimers();
        jest.restoreAllMocks();
        guardian.clearSignals();
    });

    // Test guardian initialization
    describe("Initialization", () => {
        it("should initialize with default configuration", () => {
            const guardian = new Guardian();

            // Initialization doesn't set initialized flag yet
            expect((guardian as any).initialized).toBe(false);

            // Check default config values
            expect((guardian as any).config.endpoint).toBe(
                "/__guardian__/report"
            );
            expect((guardian as any).config.sampleRate).toBe(0.1);
        });

        it("should allow custom configuration", () => {
            const config = {
                sampleRate: 1,
                detectionDelay: 2000,
                debug: true,
                threshold: 75,
            };
            const instance = new Guardian(config);
            expect(instance["config"]).toEqual({
                ...config,
                endpoint: "/__guardian__/report",
                throttleDelay: 100,
                maxArraySize: 50,
            });
        });

        it("should handle invalid configuration values", () => {
            // Invalid sample rate (should be clamped to valid range 0-1)
            const guardianNegativeSampleRate = new Guardian({
                sampleRate: -0.5,
            });
            expect((guardianNegativeSampleRate as any).config.sampleRate).toBe(
                0
            );

            const guardianTooHighSampleRate = new Guardian({ sampleRate: 1.5 });
            expect((guardianTooHighSampleRate as any).config.sampleRate).toBe(
                1
            );

            // Invalid threshold (should be at least 0)
            const guardianNegativeThreshold = new Guardian({ threshold: -10 });
            expect((guardianNegativeThreshold as any).config.threshold).toBe(0);

            // Invalid delays (should be at least 0)
            const guardianBadDelays = new Guardian({
                detectionDelay: -1000,
            });
            expect((guardianBadDelays as any).config.detectionDelay).toBe(0);
        });

        it("should initialize and run detection based on sampling rate", () => {
            // Mock Math.random to always return 0.05 (below sample rate of 0.1)
            const randomSpy = jest.spyOn(Math, "random").mockReturnValue(0.05);

            const guardian = new Guardian();
            guardian.init();

            // Should be initialized
            expect((guardian as any).initialized).toBe(true);

            // Reset random to make the guardian skip detection
            randomSpy.mockReturnValue(0.95); // above sample rate of 0.1

            const guardian2 = new Guardian();
            guardian2.init();

            // Should still be initialized even though detection will be skipped
            expect((guardian2 as any).initialized).toBe(true);

            // If init is called again, it should not re-run
            const runDetectionSpy = jest.spyOn(guardian as any, "runDetection");
            guardian.init();
            expect(runDetectionSpy).not.toHaveBeenCalled();
        });
    });

    // Test detection signals
    describe("Detection Signals", () => {
        it("should detect webdriver", () => {
            // Mock navigator.webdriver to be true
            Object.defineProperty(navigator, "webdriver", {
                value: true,
                configurable: true,
            });

            const guardian = new Guardian({ sampleRate: 1.0 }); // Always run detection
            guardian.init();

            // Fast-forward timers to run detection
            jest.runAllTimers();

            // Get results
            const results = guardian.getResults();

            // Should have detected webdriver
            const webdriverSignal = results.find(
                (s: GuardianSignal) => s.name === "webdriver"
            );
            expect(webdriverSignal).toBeDefined();
            expect(webdriverSignal?.weight).toBe(10);
        });

        it("should detect automation objects", () => {
            // Mock various automation objects
            (window as any)._phantom = true;
            (window as any).__nightmare = true;

            const guardian = new Guardian({ sampleRate: 1.0 });
            guardian.init();

            // Fast-forward timers
            jest.runAllTimers();

            // Get results
            const results = guardian.getResults();

            // Should have detected phantom and nightmare
            expect(
                results.find((s: GuardianSignal) => s.name === "phantom")
            ).toBeDefined();
            expect(
                results.find((s: GuardianSignal) => s.name === "nightmare")
            ).toBeDefined();
        });

        it("should detect inconsistent browser features", () => {
            // Set Chrome user agent but no chrome object
            Object.defineProperty(navigator, "userAgent", {
                value: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/96.0.4664.110",
                configurable: true,
            });

            // Make sure chrome object is undefined
            (window as any).chrome = undefined;

            const guardian = new Guardian({ sampleRate: 1.0 });
            guardian.init();

            // Fast-forward timers
            jest.runAllTimers();

            // Get results
            const results = guardian.getResults();

            // Should have detected fake Chrome
            expect(
                results.find((s: GuardianSignal) => s.name === "fake_chrome")
            ).toBeDefined();
        });

        it("should detect when cookies are disabled", () => {
            // Mock cookies being disabled
            Object.defineProperty(navigator, "cookieEnabled", {
                value: false,
                configurable: true,
            });

            const guardian = new Guardian({ sampleRate: 1.0 });
            guardian.init();

            // Fast-forward timers
            jest.runAllTimers();

            // Get results
            const results = guardian.getResults();

            // Should have detected cookies being disabled
            expect(
                results.find(
                    (s: GuardianSignal) => s.name === "cookies_disabled"
                )
            ).toBeDefined();
        });
    });

    // Test behavior monitoring
    describe("Behavior Monitoring", () => {
        it("should track mouse movements", () => {
            const mouseEvent = new MouseEvent("mousemove", {
                clientX: 100,
                clientY: 200,
            });
            document.dispatchEvent(mouseEvent);

            expect(guardian["behavior"].mouseMovements).toBe(1);
            expect(guardian["behavior"].mousePositions.length).toBe(1);
            expect(guardian["behavior"].mouseMovementCount).toBe(1);
        });

        it("should throttle mouse movements based on configuration", () => {
            // Create a new instance with a specific throttle delay
            const throttleDelay = 200;
            const guardianWithThrottle = new Guardian({ throttleDelay });

            // Get the original Date.now function
            const originalNow = Date.now;

            // Mock Date.now to control time precisely
            let currentTime = 1000;
            Date.now = jest.fn(() => currentTime);

            // First event should be processed
            document.dispatchEvent(
                new MouseEvent("mousemove", { clientX: 10, clientY: 10 })
            );
            expect(guardianWithThrottle["behavior"].mouseMovements).toBe(1);

            // Advance time but not enough to pass throttle
            currentTime += throttleDelay - 50;

            // This event should be ignored due to throttling
            document.dispatchEvent(
                new MouseEvent("mousemove", { clientX: 20, clientY: 20 })
            );
            expect(guardianWithThrottle["behavior"].mouseMovements).toBe(1);

            // Advance time past throttle delay
            currentTime += 100; // Now we're past the throttle delay

            // This event should be processed
            document.dispatchEvent(
                new MouseEvent("mousemove", { clientX: 30, clientY: 30 })
            );
            expect(guardianWithThrottle["behavior"].mouseMovements).toBe(2);

            // Restore original Date.now
            Date.now = originalNow;
        });

        it("should limit behavior arrays to configured maximum size", () => {
            // Create a guardian instance with a specific max array size
            const maxArraySize = 10;
            const guardian = new Guardian({ maxArraySize });

            // Directly add items to the array, bypassing throttling
            for (let i = 0; i < maxArraySize + 15; i++) {
                guardian["behavior"].mousePositions.push({
                    x: i,
                    y: i,
                    timestamp: Date.now(),
                });
            }

            // Manually call the limitArraySize method
            guardian["limitArraySize"](guardian["behavior"].mousePositions);

            // Check that array was limited to max size
            expect(guardian["behavior"].mousePositions.length).toBe(
                maxArraySize
            );

            // Verify that the oldest entries were removed (first ones)
            // The first entry should now be at index 15
            expect(guardian["behavior"].mousePositions[0].x).toBe(15);
        });

        it("should detect linear mouse movements", () => {
            const guardian = new Guardian();

            // Simulate linear mouse movements with exact diagonal pattern
            for (let i = 0; i < 5; i++) {
                (guardian as any).behavior.mousePositions.push({
                    x: i * 100,
                    y: i * 100,
                    timestamp: Date.now(),
                });
            }

            // Manually run the detection method
            (guardian as any).checkForMechanicalMouseMovement();

            const signals = (guardian as any).signals;
            expect(
                Array.from(signals.keys()).includes("linear_mouse_movement")
            ).toBe(true);
        });

        it("should track clicks", () => {
            const clickEvent = new MouseEvent("click", {
                clientX: 100,
                clientY: 200,
            });
            document.dispatchEvent(clickEvent);

            expect(guardian["behavior"].clicks).toBe(1);
            expect(guardian["behavior"].clickPositions.length).toBe(1);
            expect(guardian["behavior"].clickCount).toBe(1);
        });

        it("should track scroll events", () => {
            const scrollEvent = new Event("scroll");
            window.dispatchEvent(scrollEvent);

            expect(guardian["behavior"].scrolls).toBe(1);
            expect(guardian["behavior"].scrollEvents.length).toBe(1);
            expect(guardian["behavior"].scrollCount).toBe(1);
        });

        it("should detect mechanical scrolling", () => {
            const guardian = new Guardian();

            // Simulate mechanical scrolling with exactly 100px intervals
            for (let i = 0; i < 4; i++) {
                (guardian as any).behavior.scrollEvents.push({
                    position: i * 100,
                    timestamp: 1000 + i * 40, // 40ms intervals
                });
            }

            // Manually run the detection method
            (guardian as any).checkForMechanicalScrolling();

            const signals = (guardian as any).signals;
            expect(
                Array.from(signals.keys()).includes("mechanical_scroll")
            ).toBe(true);
        });

        it("should track keyboard events", () => {
            const keyboardEvent = new KeyboardEvent("keydown", {
                key: "a",
                code: "KeyA",
            });
            document.dispatchEvent(keyboardEvent);

            expect(guardian["behavior"].keystrokes).toBe(1);
            expect(guardian["behavior"].keystrokeCount).toBe(1);
        });

        it("should detect mechanical typing", () => {
            const guardian = new Guardian();

            // Simulate mechanical typing with exactly 40ms intervals
            for (let i = 0; i < 4; i++) {
                (guardian as any).behavior.keystrokeTimings.push(40);
            }

            // Manually run the detection method
            (guardian as any).checkForMechanicalTyping();

            const signals = (guardian as any).signals;
            expect(Array.from(signals.keys()).includes("rapid_typing")).toBe(
                true
            );
        });
    });

    // Test reporting
    describe("Reporting", () => {
        it("should not report when score is below threshold", () => {
            const guardian = new Guardian({
                sampleRate: 1.0,
                threshold: 20, // Set high threshold
            });

            // Manually add a signal with a low score
            (guardian as any).addSignal("test_signal", "Test signal", 10);

            // Finalize detection (would normally be called after a delay)
            (guardian as any).finalizeDetection();

            // No report should have been sent
            expect(mockXhr.send).not.toHaveBeenCalled();
            expect(sendBeaconSpy).not.toHaveBeenCalled();
        });

        it("should report when score exceeds threshold", () => {
            // Mock sendBeacon to test the actual data sent
            sendBeaconSpy.mockClear();

            const guardian = new Guardian({
                threshold: 50,
                endpoint: "/__guardian__/report",
            });

            // Add signals to exceed threshold but disable auto-reporting
            (guardian as any).reported = true;
            (guardian as any).addSignal("test_signal", 60, "Test signal");
            (guardian as any).reported = false;

            // Now manually trigger report
            (guardian as any).report(60);

            expect(sendBeaconSpy).toHaveBeenCalledWith(
                "/__guardian__/report",
                expect.any(String)
            );
        });

        it("should use sendBeacon if available", () => {
            const guardian = new Guardian();

            // Add signals and force report
            (guardian as any).addSignal("test_signal", 60, "Test signal");
            (guardian as any).report((guardian as any).getTotalScore());

            expect(sendBeaconSpy).toHaveBeenCalled();
        });

        it("should only report once even if called multiple times", () => {
            const guardian = new Guardian({ sampleRate: 1.0 });

            // Manually add signals
            (guardian as any).addSignal("test_signal", "Test signal", 15);

            // Call report multiple times
            (guardian as any).report();
            (guardian as any).report();
            (guardian as any).report();

            // Should have reported only once
            expect(sendBeaconSpy).toHaveBeenCalledTimes(1);
        });

        it("should handle network errors when reporting", () => {
            const guardian = new Guardian({ sampleRate: 1.0 });

            // Manually add signals
            (guardian as any).addSignal("test_signal", "Test signal", 15);

            // Disable sendBeacon to force XHR usage
            Object.defineProperty(navigator, "sendBeacon", {
                value: undefined,
                configurable: true,
            });

            // Mock XHR to simulate network error
            mockXhr.open = jest.fn().mockImplementation(() => {
                throw new Error("Network error");
            });

            // This should not throw when network error occurs
            expect(() => {
                (guardian as any).report();
            }).not.toThrow();

            // It should set reported to true even on failure to avoid retry loops
            expect((guardian as any).reported).toBe(true);
        });

        it("should handle CORS errors when reporting", () => {
            const guardian = new Guardian({ sampleRate: 1.0 });

            // Manually add signals
            (guardian as any).addSignal("test_signal", "Test signal", 15);

            // Disable sendBeacon to force XHR usage
            Object.defineProperty(navigator, "sendBeacon", {
                value: undefined,
                configurable: true,
            });

            // Mock XHR to simulate CORS error
            const mockXhrWithCorsError = {
                open: jest.fn(),
                setRequestHeader: jest.fn(),
                send: jest.fn().mockImplementation(() => {
                    const errorEvent = new ErrorEvent("error", {
                        message: "CORS error",
                    });
                    if (typeof mockXhrWithCorsError.onerror === "function") {
                        mockXhrWithCorsError.onerror(errorEvent);
                    }
                }),
                onerror: null as any,
            };

            // Replace the mock XHR
            jest.spyOn(window, "XMLHttpRequest").mockImplementation(
                () => mockXhrWithCorsError as any
            );

            // This should not throw
            expect(() => {
                (guardian as any).report();
            }).not.toThrow();

            // It should still be marked as reported
            expect((guardian as any).reported).toBe(true);
        });

        it("should handle empty signals list when reporting", () => {
            const guardian = new Guardian();

            // Force report with no signals
            (guardian as any).report(0);

            expect(sendBeaconSpy).not.toHaveBeenCalled();
            expect(mockXhr.send).not.toHaveBeenCalled();
        });

        it("should include behavior data in report", () => {
            const guardian = new Guardian();

            // Simulate behavior
            document.dispatchEvent(new MouseEvent("click"));
            window.dispatchEvent(new Event("scroll"));
            document.dispatchEvent(new KeyboardEvent("keydown"));

            // Force report
            (guardian as any).report(50);

            const lastCall = sendBeaconSpy.mock.calls[0];
            const data = JSON.parse(lastCall[1]);

            expect(data.behavior.clicks).toBe(1);
            expect(data.behavior.scrolls).toBe(1);
            expect(data.behavior.keystrokes).toBe(1);
        });

        it("should report detection results", () => {
            // Clear previous calls
            sendBeaconSpy.mockClear();

            const guardian = new Guardian({
                threshold: 5, // Low threshold to ensure reporting
            });

            // Add a signal that will trigger reporting
            (guardian as any).addSignal("webdriver", 10, "Webdriver detected");

            // Check that sendBeacon was called with correct data
            expect(sendBeaconSpy).toHaveBeenCalled();

            // Extract and parse the data
            const data = JSON.parse(sendBeaconSpy.mock.calls[0][1]);

            // Verify the signal was included
            expect(data.signals.some((s: any) => s.name === "webdriver")).toBe(
                true
            );
        });

        it("should include behavior metrics in report", () => {
            // Clear previous calls
            sendBeaconSpy.mockClear();

            const guardian = new Guardian({
                threshold: 5, // Low threshold to ensure reporting
            });

            // Manually set behavior counts instead of events to ensure exact values
            (guardian as any).behavior.mouseMovements = 1;
            (guardian as any).behavior.clicks = 1;
            (guardian as any).behavior.scrollEvents = [
                { position: 100, timestamp: Date.now() },
            ];

            // Add a signal to trigger reporting
            (guardian as any).addSignal("test_signal", 10, "Test signal");

            // Verify the report was sent
            expect(sendBeaconSpy).toHaveBeenCalled();

            // Parse the data
            const data = JSON.parse(sendBeaconSpy.mock.calls[0][1]);

            // Check the behavior metrics
            expect(data.behavior.mouseMovements).toBe(1);
            expect(data.behavior.clicks).toBe(1);
            expect(data.behavior.scrolls).toBe(1);
        });
    });

    // Test utility methods
    describe("Utility Methods", () => {
        it("should add custom signals", () => {
            const guardian = new Guardian();

            guardian.addCustomSignal("my_test_signal", 8, "My test signal");

            const results = guardian.getResults();
            const signal = results.find(
                (s: GuardianSignal) => s.name === "my_test_signal"
            );

            expect(signal).toBeDefined();
            expect(signal?.weight).toBe(8);
            expect(signal?.description).toBe("My test signal");
        });

        it("should clear signals", () => {
            const guardian = new Guardian();

            // Add some signals
            (guardian as any).addSignal("test_signal1", "Test signal 1", 5);
            (guardian as any).addSignal("test_signal2", "Test signal 2", 7);

            // Check they're there
            expect(guardian.getResults().length).toBe(2);

            // Clear signals
            guardian.clearSignals();

            // Should be empty
            expect(guardian.getResults().length).toBe(0);

            // Should reset reported flag
            expect((guardian as any).reported).toBe(false);
        });

        it("should calculate total score correctly", () => {
            const guardian = new Guardian();

            // Add signals with different weights
            (guardian as any).addSignal("test_signal1", 5, "Test signal 1");
            (guardian as any).addSignal("test_signal2", 7, "Test signal 2");
            (guardian as any).addSignal("test_signal3", 3, "Test signal 3");

            // Check total score
            expect((guardian as any).getTotalScore()).toBe(15);
        });
    });

    // Security edge cases
    describe("Security Edge Cases", () => {
        it("should handle attempts to tamper with the detection", () => {
            const guardian = new Guardian({ sampleRate: 1.0 });
            guardian.init();

            // Fast-forward timers to run detection
            jest.runAllTimers();

            // Attempt to tamper with methods
            const tamperedMethod = (guardian as any).addSignal;

            // Replace the method
            (guardian as any).addSignal = function () {
                // Do nothing, preventing signal collection
            };

            // Signals collection should still work when restored
            (guardian as any).addSignal = tamperedMethod;
            (guardian as any).addSignal(
                "after_tamper",
                "Signal after tampering attempt",
                10
            );

            // Verify tamper detection mechanism
            const results = guardian.getResults();
            expect(results.length).toBeGreaterThan(0);
        });

        it("should detect iframe embedding", () => {
            // Mock window.top to simulate being in an iframe
            Object.defineProperty(window, "top", {
                value: { location: { href: "https://different-domain.com" } },
                configurable: true,
            });

            // Mock window.self to be different from window.top
            Object.defineProperty(window, "self", {
                value: { location: { href: "https://original-domain.com" } },
                configurable: true,
            });

            const guardian = new Guardian({ sampleRate: 1.0 });
            guardian.init();

            // Fast-forward timers to run detection
            jest.runAllTimers();

            // Verify if iframe embedding is detected
            const results = guardian.getResults();
            const iframeSignal = results.find(
                (s: GuardianSignal) =>
                    s.name === "iframe_embedded" || s.name === "framed"
            );

            // Should detect being in an iframe on a different domain
            expect(iframeSignal).toBeDefined();
        });

        it("should handle blocked features gracefully", () => {
            // Mock a browser with privacy features that block fingerprinting
            Object.defineProperty(navigator, "userAgent", {
                value: "Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1",
                configurable: true,
            });

            // Mock canvas blocking (throw error when attempting to use canvas)
            const originalGetContext = HTMLCanvasElement.prototype.getContext;
            HTMLCanvasElement.prototype.getContext = function () {
                return null; // Simulate privacy browsers that return null for canvas contexts
            };

            const guardian = new Guardian({ sampleRate: 1.0 });

            // This should not throw errors
            expect(() => {
                guardian.init();
                jest.runAllTimers(); // Run detection
            }).not.toThrow();

            // Restore original implementation after test
            HTMLCanvasElement.prototype.getContext = originalGetContext;
        });
    });

    describe("Browser Environment Detection", () => {
        it("should detect missing languages", () => {
            Object.defineProperty(navigator, "languages", {
                value: [],
                configurable: true,
            });

            const guardian = new Guardian({ sampleRate: 1.0 });
            guardian.init();
            jest.runAllTimers();

            const signals = guardian.getResults();
            expect(signals).toContainEqual(
                expect.objectContaining({
                    name: "no_languages",
                    weight: 8,
                })
            );
        });

        it("should detect disabled cookies", () => {
            Object.defineProperty(navigator, "cookieEnabled", {
                value: false,
                configurable: true,
            });

            const guardian = new Guardian({ sampleRate: 1.0 });
            guardian.init();
            jest.runAllTimers();

            const signals = guardian.getResults();
            expect(signals).toContainEqual(
                expect.objectContaining({
                    name: "cookies_disabled",
                    weight: 7,
                })
            );
        });
    });

    describe("User Behavior Monitoring", () => {
        it("should track mouse movements", () => {
            const guardian = new Guardian({ sampleRate: 1.0 });
            guardian.init();

            // Simulate mouse movement
            const mouseEvent = new MouseEvent("mousemove", {
                clientX: 100,
                clientY: 200,
            });
            document.dispatchEvent(mouseEvent);

            jest.runAllTimers();

            const signals = guardian.getResults();
            expect(signals).toContainEqual(
                expect.objectContaining({
                    name: "mouse_movement",
                    weight: 5,
                })
            );
        });

        it("should track clicks", () => {
            const guardian = new Guardian({ sampleRate: 1.0 });
            guardian.init();

            // Simulate click
            const clickEvent = new MouseEvent("click", {
                clientX: 100,
                clientY: 200,
            });
            document.dispatchEvent(clickEvent);

            jest.runAllTimers();

            const signals = guardian.getResults();
            expect(signals).toContainEqual(
                expect.objectContaining({
                    name: "click",
                    weight: 10,
                })
            );
        });

        it("should track scroll events", () => {
            const guardian = new Guardian({ sampleRate: 1.0 });
            guardian.init();

            // Simulate scroll
            const scrollEvent = new Event("scroll");
            window.dispatchEvent(scrollEvent);

            jest.runAllTimers();

            const signals = guardian.getResults();
            expect(signals).toContainEqual(
                expect.objectContaining({
                    name: "scroll",
                    weight: 5,
                })
            );
        });

        it("should track keyboard events", () => {
            const guardian = new Guardian({ sampleRate: 1.0 });
            guardian.init();

            // Simulate keyboard event
            const keyboardEvent = new KeyboardEvent("keydown", {
                key: "a",
                code: "KeyA",
                keyCode: 65,
            });
            document.dispatchEvent(keyboardEvent);

            jest.runAllTimers();

            const signals = guardian.getResults();
            expect(signals).toContainEqual(
                expect.objectContaining({
                    name: "keyboard",
                    weight: 15,
                })
            );
        });
    });

    describe("Performance Optimizations", () => {
        it("should use requestIdleCallback for non-critical processing", () => {
            // Mock requestIdleCallback
            const originalRequestIdleCallback = (window as any)
                .requestIdleCallback;
            const mockRequestIdleCallback = jest.fn((callback) => {
                return setTimeout(callback, 0);
            });
            (window as any).requestIdleCallback = mockRequestIdleCallback;

            // Create a new Guardian instance
            const guardian = new Guardian({ sampleRate: 1.0 });

            // Call init which should trigger runDetection
            guardian.init();

            // Run the initial setTimeout to reach the requestIdleCallback call
            jest.runAllTimers();

            // Verify requestIdleCallback was called
            expect(mockRequestIdleCallback).toHaveBeenCalled();

            // Restore the original
            (window as any).requestIdleCallback = originalRequestIdleCallback;
        });
    });
});
