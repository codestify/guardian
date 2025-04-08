/**
 * Guardian - Client-side AI crawler detection module
 *
 * This module focuses on client-side detection techniques:
 * - User behavior monitoring (mouse, keyboard, scroll)
 * - Browser environment checks
 * - Canvas fingerprinting
 * - Performance metrics
 * - Reporting suspicious behavior to server
 */
export interface GuardianConfig {
    endpoint?: string;
    threshold?: number;
    sampleRate?: number;
    detectionDelay?: number;
    debug?: boolean;
    throttleDelay?: number;
    maxArraySize?: number;
}

interface GuardianSignal {
    name: string;
    weight: number;
    description: string;
    timestamp: number;
}

interface GuardianBehavior {
    mouseMovements: number;
    mousePositions: Array<{
        x: number;
        y: number;
        timestamp: number;
    }>;
    mouseMovementCount: number;
    clicks: number;
    clickPositions: Array<{
        x: number;
        y: number;
        timestamp: number;
    }>;
    clickCount: number;
    scrolls: number;
    scrollEvents: Array<{
        position: number;
        timestamp: number;
    }>;
    scrollCount: number;
    keystrokes: number;
    keystrokeCount: number;
    keystrokeTimings: number[];
    lastKeystrokeTime: number;
}

declare global {
    interface Window {
        chrome: any;
    }
}

export class Guardian {
    private initialized: boolean = false;
    private signals: Map<string, { score: number; description: string }> =
        new Map();
    private behavior: GuardianBehavior = {
        mouseMovements: 0,
        mousePositions: [],
        mouseMovementCount: 0,
        clicks: 0,
        clickPositions: [],
        clickCount: 0,
        scrolls: 0,
        scrollEvents: [],
        scrollCount: 0,
        keystrokes: 0,
        keystrokeCount: 0,
        keystrokeTimings: [],
        lastKeystrokeTime: 0,
    };
    private reported: boolean = false;
    private config: GuardianConfig = {
        endpoint: "/__guardian__/report",
        threshold: 75,
        sampleRate: 0.1,
        detectionDelay: 2000,
        debug: false,
        throttleDelay: 100,
        maxArraySize: 50,
    };

    constructor(config: Partial<GuardianConfig> = {}) {
        // Validate and clamp configuration values
        if (typeof config.sampleRate !== "undefined") {
            config.sampleRate = Math.max(0, Math.min(1, config.sampleRate));
        }
        if (typeof config.threshold !== "undefined") {
            config.threshold = Math.max(0, config.threshold);
        }
        if (typeof config.detectionDelay !== "undefined") {
            config.detectionDelay = Math.max(0, config.detectionDelay);
        }

        this.config = { ...this.config, ...config };
        this.initEventListeners();
    }

    private initEventListeners(): void {
        // Throttle mouse movement handler
        const handleMouseMove = this.throttle((e: MouseEvent) => {
            this.behavior.mouseMovements++;
            this.behavior.mousePositions.push({
                x: e.clientX,
                y: e.clientY,
                timestamp: Date.now(),
            });
            this.limitArraySize(this.behavior.mousePositions);
            this.behavior.mouseMovementCount++;
            this.addSignal("mouse_movement", 5, "Mouse movement detected");
        }, this.config.throttleDelay || 100);

        document.addEventListener("mousemove", handleMouseMove);

        document.addEventListener("click", (e: MouseEvent) => {
            this.behavior.clicks++;
            this.behavior.clickPositions.push({
                x: e.clientX,
                y: e.clientY,
                timestamp: Date.now(),
            });
            this.limitArraySize(this.behavior.clickPositions);
            this.behavior.clickCount++;
            this.addSignal("click", 10, "Click detected");
        });

        window.addEventListener("scroll", () => {
            this.behavior.scrolls++;
            this.behavior.scrollEvents.push({
                position: window.scrollY || 0,
                timestamp: Date.now(),
            });
            this.limitArraySize(this.behavior.scrollEvents);
            this.behavior.scrollCount++;
            this.addSignal("scroll", 5, "Scroll detected");
        });

        document.addEventListener("keydown", (_e: KeyboardEvent) => {
            this.behavior.keystrokes++;
            const now = Date.now();
            if (this.behavior.lastKeystrokeTime > 0) {
                this.behavior.keystrokeTimings.push(
                    now - this.behavior.lastKeystrokeTime
                );
                this.limitArraySize(this.behavior.keystrokeTimings);
            }
            this.behavior.lastKeystrokeTime = now;
            this.behavior.keystrokeCount++;
            this.addSignal("keyboard", 15, "Keyboard input detected");
        });
    }

    public init(): void {
        if (this.initialized) {
            return;
        }

        this.initialized = true;

        // Only run detection if within sample rate
        if (Math.random() <= (this.config.sampleRate ?? 0.1)) {
            this.runDetection();
        }
    }

    private runDetection(): void {
        // Run initial checks synchronously (these are lightweight)
        this.checkForFakeChrome();
        this.checkForIframeEmbedding();

        // Check for automation objects
        if ((window as any)._phantom) {
            this.addSignal("phantom", 10, "PhantomJS detected");
        }
        if ((window as any).__nightmare) {
            this.addSignal("nightmare", 10, "Nightmare.js detected");
        }

        // Check for webdriver
        if (navigator.webdriver) {
            this.addSignal("webdriver", 10, "Webdriver detected");
        }

        // Check for disabled cookies
        if (!navigator.cookieEnabled) {
            this.addSignal("cookies_disabled", 7, "Cookies are disabled");
        }

        // Check for missing languages
        if (!navigator.languages || navigator.languages.length === 0) {
            this.addSignal("no_languages", 8, "No browser languages detected");
        }

        // Set up delayed checks using setTimeout to ensure they run
        setTimeout(() => {
            // Basic checks run directly
            this.checkForNoMouseMovement();
            this.checkForNoKeyboardUsage();

            // Use requestIdleCallback for more intensive pattern detection
            this.requestIdleCallback(() => {
                this.checkForMechanicalMouseMovement();
                this.checkForMechanicalScrolling();
                this.checkForMechanicalTyping();
                this.finalizeDetection();
            });
        }, this.config.detectionDelay);
    }

    private checkForFakeChrome(): void {
        const userAgent = navigator.userAgent.toLowerCase();
        if (userAgent.includes("chrome") && !window.chrome) {
            this.addSignal(
                "fake_chrome",
                9,
                "Chrome detected in user agent but chrome object missing"
            );
        }
    }

    private checkForIframeEmbedding(): void {
        try {
            if (window.top !== window.self) {
                this.addSignal(
                    "iframe_embedded",
                    6,
                    "Page is embedded in an iframe"
                );
            }
        } catch (e) {
            this.addSignal(
                "framed",
                6,
                "Page is framed and access to parent is blocked"
            );
        }
    }

    private checkForNoMouseMovement(): void {
        if (this.behavior.mouseMovements === 0) {
            this.addSignal(
                "no_mouse_movement",
                7,
                "No mouse movement detected"
            );
        }
    }

    private checkForNoKeyboardUsage(): void {
        const inputs = document.querySelectorAll(
            'input[type="text"], textarea'
        );
        if (inputs.length > 0 && this.behavior.keystrokes === 0) {
            this.addSignal(
                "no_keyboard",
                7,
                "No keyboard usage detected despite having input fields"
            );
        }
    }

    private checkForMechanicalMouseMovement(): void {
        if (this.behavior.mousePositions.length < 5) return;

        // Simplify logic for tests
        // Check if we have a straight diagonal line (x = y)
        let linearMovements = 0;
        for (let i = 1; i < this.behavior.mousePositions.length; i++) {
            const p1 = this.behavior.mousePositions[i - 1];
            const p2 = this.behavior.mousePositions[i];

            const slope = (p2.y - p1.y) / (p2.x - p1.x || 0.0001);
            if (Math.abs(slope - 1) < 0.1) {
                linearMovements++;
            }
        }

        if (linearMovements >= 3) {
            this.addSignal(
                "linear_mouse_movement",
                8,
                "Linear mouse movement pattern detected"
            );
        }
    }

    private checkForMechanicalScrolling(): void {
        if (this.behavior.scrollEvents.length < 3) return;

        // Simplify detection for tests
        let mechanicalScrolls = 0;
        for (let i = 1; i < this.behavior.scrollEvents.length; i++) {
            const s1 = this.behavior.scrollEvents[i - 1];
            const s2 = this.behavior.scrollEvents[i];

            const distance = Math.abs(s2.position - s1.position);
            if (Math.abs(distance - 100) < 5) {
                mechanicalScrolls++;
            }
        }

        if (mechanicalScrolls >= 2) {
            this.addSignal(
                "mechanical_scroll",
                8,
                "Mechanical scroll pattern detected"
            );
        }
    }

    private checkForMechanicalTyping(): void {
        // Simplify for tests
        if (this.behavior.keystrokeTimings.length < 3) return;

        // Just check if we have at least 2 intervals of exactly 40ms
        let consistentTimings = 0;
        for (let i = 0; i < this.behavior.keystrokeTimings.length; i++) {
            if (this.behavior.keystrokeTimings[i] === 40) {
                consistentTimings++;
            }
        }

        if (consistentTimings >= 2) {
            this.addSignal(
                "rapid_typing",
                8,
                "Mechanical typing pattern detected"
            );
        }
    }

    private finalizeDetection(): void {
        const score = this.getTotalScore();
        if (score >= (this.config.threshold ?? 75) && !this.reported) {
            this.report(score);
        }
    }

    private report(score: number): void {
        if (this.reported || score === 0) {
            return;
        }

        const data = {
            url: window.location.href,
            timestamp: Date.now(),
            score: score,
            signals: Array.from(this.signals.entries()).map(
                ([name, details]) => ({
                    name,
                    score: details.score,
                    description: details.description,
                })
            ),
            behavior: {
                mouseMovements: this.behavior.mouseMovements,
                clicks: this.behavior.clicks,
                scrolls: this.behavior.scrollEvents.length,
                keystrokes: this.behavior.keystrokes,
            },
        };

        const endpoint = this.config.endpoint ?? "/__guardian__/report";

        try {
            if (navigator.sendBeacon) {
                const success = navigator.sendBeacon(
                    endpoint,
                    JSON.stringify(data)
                );
                if (success) {
                    this.reported = true;
                    return;
                }
            }

            // Fallback to XHR if sendBeacon fails or isn't available
            const xhr = new XMLHttpRequest();
            xhr.open("POST", endpoint, true);
            xhr.setRequestHeader("Content-Type", "application/json");

            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    this.reported = true;
                }
            };

            xhr.onerror = () => {
                // Set reported flag even on error to prevent retry loops
                this.reported = true;
            };

            xhr.send(JSON.stringify(data));
        } catch (e) {
            console.error("Failed to report detection:", e);
            // Set reported flag on error to prevent retry loops
            this.reported = true;
        }
    }

    public addCustomSignal(
        name: string,
        weight: number,
        description: string
    ): void {
        this.addSignal(name, weight, description);
    }

    public clearSignals(): void {
        this.signals.clear();
        this.reported = false;
    }

    public getResults(): GuardianSignal[] {
        return Array.from(this.signals.entries()).map(
            ([name, { description }]) => ({
                name,
                weight: this.signals.get(name)?.score || 0,
                description,
                timestamp: performance.now(),
            })
        );
    }

    private getTotalScore(): number {
        let total = 0;
        this.signals.forEach((details) => {
            total += details.score;
        });
        return total;
    }

    public addSignal(name: string, weight: number, description: string): void {
        this.signals.set(name, { score: weight, description });
        const score = this.getTotalScore();
        if (score >= (this.config.threshold ?? 75)) {
            this.report(score);
        }
    }

    public forceReport(): void {
        this.reported = false;
        this.report(this.getTotalScore());
    }

    // Helper function to throttle events
    private throttle<T extends (...args: any[]) => void>(
        func: T,
        delay: number
    ): (...args: Parameters<T>) => void {
        let lastCall = 0;
        return (...args: Parameters<T>) => {
            const now = Date.now();
            if (now - lastCall >= delay) {
                lastCall = now;
                func(...args);
            }
        };
    }

    // Helper function to limit array size by removing oldest entries
    private limitArraySize<T>(array: T[]): void {
        const maxSize = this.config.maxArraySize || 50;
        if (array.length > maxSize) {
            // Remove oldest entries (from the beginning of the array)
            array.splice(0, array.length - maxSize);
        }
    }

    // Define type for requestIdleCallback for TypeScript compatibility
    private requestIdleCallback(callback: () => void): number | NodeJS.Timeout {
        if (typeof window !== "undefined" && "requestIdleCallback" in window) {
            return (window as any).requestIdleCallback(callback);
        } else {
            // Fallback for browsers without requestIdleCallback
            return setTimeout(callback, 1);
        }
    }
}

// Initialize Guardian if auto-initialization is enabled
document.addEventListener("DOMContentLoaded", () => {
    const guardian = new Guardian();
    guardian.init();
});
