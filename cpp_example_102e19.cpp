// Learning Objective: This tutorial demonstrates how to create a visual explorer
// for generating and manipulating Mandelbrot fractals in C++.
// It focuses on teaching the fundamental concepts of recursion (implicitly)
// and complex number arithmetic, which are essential for understanding fractals.
// We will use a simple ray casting approach to visualize the fractal.

#include <iostream>
#include <complex> // For complex number operations
#include <vector>  // To store pixel data
#include <cmath>   // For mathematical functions like abs

// Define the resolution of our fractal image.
// These are just for visualization; the fractal itself is infinite.
const int WIDTH = 800;
const int HEIGHT = 600;

// The maximum number of iterations to determine if a point is in the set.
// Higher values reveal more detail but take longer to compute.
const int MAX_ITERATIONS = 100;

// This function determines if a complex number 'c' belongs to the Mandelbrot set.
// The Mandelbrot set is defined by the condition that for a complex number 'c',
// the sequence defined by z_n+1 = z_n^2 + c, with z_0 = 0, does not diverge to infinity.
// We check this by seeing how many iterations it takes for the magnitude of 'z' to exceed a certain threshold (typically 2).
// The 'iterations' output parameter will store how many steps it took to diverge.
int mandelbrot(const std::complex<double>& c, int& iterations) {
    std::complex<double> z(0.0, 0.0); // Initialize z_0 to 0

    // Iterate the formula z = z*z + c
    // This is where the "recursion" happens implicitly: the next value of z
    // depends on the previous value of z.
    for (int i = 0; i < MAX_ITERATIONS; ++i) {
        z = z * z + c; // Complex number multiplication and addition

        // Check if the magnitude (distance from origin) of z exceeds 2.
        // If it does, the sequence is diverging, and 'c' is NOT in the Mandelbrot set.
        // Using abs() on a complex number gives its magnitude.
        if (std::abs(z) > 2.0) {
            iterations = i; // Store how many iterations it took to diverge
            return i;       // Return the number of iterations
        }
    }

    // If the loop completes without diverging, the point 'c' is considered to be in the Mandelbrot set.
    iterations = MAX_ITERATIONS; // Mark as not diverging within the limit
    return MAX_ITERATIONS;       // Return max iterations
}

// Function to map a pixel coordinate (x, y) to a complex number 'c'.
// This allows us to explore different regions of the complex plane.
// 'zoom' controls how much we zoom in, and 'offsetX', 'offsetY' pan the view.
std::complex<double> pixelToComplex(int x, int y, double zoom, double offsetX, double offsetY) {
    // Map pixel coordinates (0 to WIDTH, 0 to HEIGHT) to a normalized range.
    // We then scale and translate this range to cover the desired part of the complex plane.
    double real = (double)x / WIDTH * 4.0 - 2.0; // Map x to [-2, 2] initially
    double imag = (double)y / HEIGHT * 4.0 - 2.0; // Map y to [-2, 2] initially

    // Apply zoom and offset
    real = real / zoom + offsetX;
    imag = imag / zoom + offsetY;

    return std::complex<double>(real, imag); // Create a complex number
}

// A simple structure to hold RGB color values.
struct Color {
    int r, g, b;
};

// Function to generate a color based on the number of iterations.
// This makes the fractal visually appealing. Points inside the set get one color,
// and points outside get a color based on how quickly they diverged.
Color getColor(int iterations) {
    if (iterations == MAX_ITERATIONS) {
        return {0, 0, 0}; // Black for points inside the Mandelbrot set
    } else {
        // Simple coloring based on iterations. Experiment with this!
        // The modulo operator (%) helps to create repeating color patterns.
        int hue = (iterations * 10) % 256;
        return {hue, hue / 2, hue / 4}; // Varying shades of color
    }
}

// This is our main rendering function. It iterates through each pixel,
// converts its coordinates to a complex number, checks if it's in the Mandelbrot set,
// and assigns a color.
void renderMandelbrot(double zoom, double offsetX, double offsetY) {
    // We could use a 2D array or a vector of vectors to store pixel data.
    // For simplicity, we'll directly print characters representing colors.
    // In a real application, you'd use a graphics library.

    for (int y = 0; y < HEIGHT; ++y) {
        for (int x = 0; x < WIDTH; ++x) {
            // Convert pixel coordinates to a complex number in the complex plane.
            std::complex<double> c = pixelToComplex(x, y, zoom, offsetX, offsetY);

            int iterations = 0;
            // Calculate the number of iterations to determine if 'c' is in the set.
            mandelbrot(c, iterations);

            // Get a color based on the iteration count.
            Color color = getColor(iterations);

            // For simplicity, we'll represent colors with characters.
            // In a real app, you'd write these RGB values to an image file or display.
            // This basic output is for demonstration purposes.
            // A more sophisticated visualizer would use a graphics API (like SFML, SDL, or OpenGL).
            if (iterations == MAX_ITERATIONS) {
                std::cout << " "; // Space for points in the set
            } else {
                std::cout << "*"; // Asterisk for points outside
            }
        }
        std::cout << std::endl; // Newline after each row of pixels
    }
}

int main() {
    std::cout << "Welcome to the Mandelbrot Fractal Explorer Tutorial!\n";
    std::cout << "This program will generate a textual representation of the Mandelbrot set.\n";
    std::cout << "Pay attention to the comments explaining complex numbers and the iteration process.\n\n";

    // --- Example Usage ---
    // These parameters control which part of the Mandelbrot set we view.

    // Default view: Full Mandelbrot set
    std::cout << "Rendering default view...\n";
    renderMandelbrot(1.0, 0.0, 0.0); // zoom=1.0, offsetX=0.0, offsetY=0.0

    std::cout << "\nRendering a zoomed-in view...\n";
    // Zoomed-in view: Focus on a specific region
    // Experiment with these values! Try zoom=10, zoom=100, etc.
    // offsetX and offsetY shift the center of the view.
    // Values for zoom and offsets are often found by exploration.
    renderMandelbrot(30.0, -0.75, 0.0);

    std::cout << "\nTutorial finished. Happy exploring!\n";

    return 0;
}