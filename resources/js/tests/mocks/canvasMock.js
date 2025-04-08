// Mock implementation of canvas
export const createCanvas = (width, height) => {
    return {
        width,
        height,
        getContext: () => ({
            fillRect: jest.fn(),
            clearRect: jest.fn(),
            getImageData: () => ({
                data: new Array(width * height * 4).fill(0),
            }),
            putImageData: jest.fn(),
            createImageData: jest.fn(() => ({
                data: new Array(width * height * 4).fill(0),
            })),
            drawImage: jest.fn(),
            fillText: jest.fn(),
            measureText: jest.fn(() => ({ width: 0 })),
            save: jest.fn(),
            restore: jest.fn(),
            translate: jest.fn(),
            rotate: jest.fn(),
            scale: jest.fn(),
        }),
    };
};

export const loadImage = jest.fn(() =>
    Promise.resolve({
        width: 100,
        height: 100,
    })
);

// Default export for compatibility
export default {
    createCanvas,
    loadImage,
};
