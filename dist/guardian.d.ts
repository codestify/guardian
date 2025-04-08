declare interface GuardianOptions {
    sampleRate?: number;
    detectionDelay?: number;
    finalizeDelay?: number;
    threshold?: number;
    debug?: boolean;
}

declare interface DetectionSignal {
    name: string;
    weight: number;
    description: string;
    timestamp?: number;
}

declare class Guardian {
    constructor(options?: GuardianOptions);
    init(): void;
    getResults(): DetectionSignal[];
    addCustomSignal(name: string, weight: number, description: string): void;
    clearSignals(): void;
}

export { Guardian as default };
