"""
Image/GIF to LED Matrix Converter
Transforms images and GIFs into Arduino code for 64x64 LED matrices
"""

from PIL import Image, ImageSequence
import numpy as np
import os
import sys
import json
import argparse

class LEDMatrixConverter:
    def __init__(self, width=64, height=64):
        self.width = width
        self.height = height
        
    def load_image(self, filepath):
        """Load and resize image to matrix dimensions, preserve alpha"""
        img = Image.open(filepath)
        img = img.convert('RGBA')
        img = img.resize((self.width, self.height), Image.Resampling.LANCZOS)
        return np.array(img)
    
    @staticmethod
    def _gif_background_color(gif):
        """Return (R,G,B,255) of the GIF's declared background palette entry."""
        try:
            idx = gif.info.get('background', 0) or 0
            pal = gif.getpalette()          # flat [R,G,B, R,G,B, ...]
            if pal and len(pal) > idx * 3 + 2:
                return (pal[idx*3], pal[idx*3+1], pal[idx*3+2], 255)
        except Exception:
            pass
        return (0, 0, 0, 255)

    def load_gif(self, filepath):
        gif = Image.open(filepath)
        bg  = self._gif_background_color(gif)
        frames = []
        canvas = Image.new('RGBA', gif.size, bg)

        for raw_frame in ImageSequence.Iterator(gif):
            disposal = raw_frame.info.get('disposal', 0)
            saved    = canvas.copy() if disposal == 3 else None

            frame_rgba = raw_frame.convert('RGBA')
            canvas.paste(frame_rgba, (0, 0), frame_rgba)

            # NEAREST keeps hard palette edges sharp; LANCZOS blurs them
            f = canvas.copy().resize((self.width, self.height), Image.Resampling.NEAREST)
            frames.append(np.array(f))

            if disposal == 2:
                canvas = Image.new('RGBA', gif.size, bg)
            elif disposal == 3 and saved:
                canvas = saved

        return frames

    def load_gif_with_durations(self, filepath):
        gif = Image.open(filepath)
        bg  = self._gif_background_color(gif)
        frames    = []
        durations = []
        canvas    = Image.new('RGBA', gif.size, bg)

        for raw_frame in ImageSequence.Iterator(gif):
            duration = raw_frame.info.get('duration', gif.info.get('duration', 0))
            if not duration:
                duration = 80
            durations.append(int(duration))

            disposal = raw_frame.info.get('disposal', 0)
            saved    = canvas.copy() if disposal == 3 else None

            frame_rgba = raw_frame.convert('RGBA')
            canvas.paste(frame_rgba, (0, 0), frame_rgba)

            f = canvas.copy().resize((self.width, self.height), Image.Resampling.NEAREST)
            frames.append(np.array(f))

            if disposal == 2:
                canvas = Image.new('RGBA', gif.size, bg)
            elif disposal == 3 and saved:
                canvas = saved

        return frames, durations
    
    def apply_effect(self, image, effect='none'):
        """Apply visual effects to the image; preserve alpha if present"""
        has_alpha = image.shape[-1] == 4
        if has_alpha:
            rgb = image[..., :3]
            alpha = image[..., 3:4]
        else:
            rgb = image
            alpha = None

        if effect == 'pixelate':
            small = Image.fromarray(rgb).resize((16, 16), Image.Resampling.NEAREST)
            out_rgb = np.array(small.resize((self.width, self.height), Image.Resampling.NEAREST))
        elif effect == 'neon':
            img_float = rgb.astype(float)
            brightness = 1.3
            out_rgb = np.clip(img_float * brightness, 0, 255).astype(np.uint8)
        elif effect == 'retro':
            levels = 4
            out_rgb = ((rgb // (256 // levels)) * (256 // levels)).astype(np.uint8)
        elif effect == 'edge':
            img = Image.fromarray(rgb).convert('L')
            edges = np.array(img)
            kernel = np.array([[-1, -1, -1], [-1, 8, -1], [-1, -1, -1]])
            result = np.zeros_like(edges)
            for i in range(1, edges.shape[0]-1):
                for j in range(1, edges.shape[1]-1):
                    result[i,j] = np.abs(np.sum(edges[i-1:i+2, j-1:j+2] * kernel))
            result = np.clip(result, 0, 255)
            out_rgb = np.stack([result, result, result], axis=-1)
        else:
            out_rgb = rgb

        if has_alpha:
            return np.concatenate([out_rgb, alpha], axis=-1)
        return out_rgb
    
    def calculate_frame_delay(self, durations, min_delay=50, max_delay=200, default_delay=80):
        valid = [d for d in durations if d and d > 0]
        if not valid:
            delay = default_delay
        else:
            delay = sum(valid) / len(valid)
        if delay < min_delay:
            delay = min_delay
        if delay > max_delay:
            delay = max_delay
        return int(delay)
    
    def get_frame_data(self, image_data):
        """Convert image data (RGB/RGBA) to list of integer colors (0xRRGGBB), alpha<128 -> off"""
        pixels = []
        h, w = image_data.shape[:2]   # use actual image dims, not self.width/height
        has_alpha = image_data.shape[-1] == 4
        for y in range(h):
            for x in range(w):
                if has_alpha:
                    r, g, b, a = image_data[y, x]
                    if int(a) < 128:
                        pixels.append(0)
                        continue
                else:
                    r, g, b = image_data[y, x]
                color = int((int(r) << 16) | (int(g) << 8) | int(b))
                pixels.append(color)
        return pixels

    def generate_arduino_code(self, image_data, is_gif=False, effect='none', frame_delay=50):
        """Generate Arduino code for the LED matrix"""
        
        # Header
        code = """#include <ESP32-HUB75-MatrixPanel-I2S-DMA.h>

// Pin definitions
#define PANEL_RES_X 64
#define PANEL_RES_Y 64
#define PANEL_CHAIN 1

#define R1_PIN 25
#define G1_PIN 26
#define B1_PIN 27
#define R2_PIN 14
#define G2_PIN 12
#define B2_PIN 13
#define A_PIN 23
#define B_PIN 19
#define C_PIN 5
#define D_PIN 18
#define E_PIN 32
#define LAT_PIN 4
#define OE_PIN 15
#define CLK_PIN 21

MatrixPanel_I2S_DMA *dma_display = nullptr;

"""
        
        # Add image data
        if is_gif:
            code += f"const int NUM_FRAMES = {len(image_data)};\n"
            code += f"const int FRAME_DELAY = {frame_delay};\n"
            code += "int currentFrame = 0;\n\n"
            
            # Store frames as PROGMEM
            for idx, frame in enumerate(image_data):
                frame = self.apply_effect(frame, effect)
                fh, fw = frame.shape[:2]
                code += f"const uint8_t frame{idx}[{fw * fh * 3}] PROGMEM = {{\n"

                pixel_data = []
                for y in range(fh):
                    for x in range(fw):
                        px = frame[y, x]
                        r, g, b = int(px[0]), int(px[1]), int(px[2])
                        pixel_data.extend([r, g, b])
                
                # Format as comma-separated values
                for i in range(0, len(pixel_data), 16):
                    chunk = pixel_data[i:i+16]
                    code += "  " + ",".join(map(str, chunk)) + ",\n"
                
                code = code.rstrip(",\n") + "\n};\n\n"
            
            # Array of frame pointers
            code += "const uint8_t* frames[] = {\n"
            for idx in range(len(image_data)):
                code += f"  frame{idx},\n"
            code += "};\n\n"
        else:
            # Single image
            image_data = self.apply_effect(image_data, effect)
            fh, fw = image_data.shape[:2]
            code += f"const uint8_t imageData[{fw * fh * 3}] PROGMEM = {{\n"

            pixel_data = []
            for y in range(fh):
                for x in range(fw):
                    px = image_data[y, x]
                    r, g, b = int(px[0]), int(px[1]), int(px[2])
                    pixel_data.extend([r, g, b])
            
            for i in range(0, len(pixel_data), 16):
                chunk = pixel_data[i:i+16]
                code += "  " + ",".join(map(str, chunk)) + ",\n"
            
            code = code.rstrip(",\n") + "\n};\n\n"
        
        # Setup function
        code += """void setup() {
  Serial.begin(115200);
  
  HUB75_I2S_CFG mxconfig(PANEL_RES_X, PANEL_RES_Y, PANEL_CHAIN);
  mxconfig.double_buff = true;
  
  mxconfig.gpio.r1 = R1_PIN;
  mxconfig.gpio.g1 = G1_PIN;
  mxconfig.gpio.b1 = B1_PIN;
  mxconfig.gpio.r2 = R2_PIN;
  mxconfig.gpio.g2 = B2_PIN;  // Swapped for your panel
  mxconfig.gpio.b2 = G2_PIN;  // Swapped for your panel
  mxconfig.gpio.a = A_PIN;
  mxconfig.gpio.b = B_PIN;
  mxconfig.gpio.c = C_PIN;
  mxconfig.gpio.d = D_PIN;
  mxconfig.gpio.e = E_PIN;
  mxconfig.gpio.lat = LAT_PIN;
  mxconfig.gpio.oe = OE_PIN;
  mxconfig.gpio.clk = CLK_PIN;
  
  dma_display = new MatrixPanel_I2S_DMA(mxconfig);
  dma_display->begin();
  dma_display->setBrightness8(64);
  dma_display->clearScreen();
  
  Serial.println("Display initialized!");
}

"""
        
        # Loop function
        if is_gif:
            code += """void loop() {
  static unsigned long lastFrameTime = 0;
  
  if(millis() - lastFrameTime > FRAME_DELAY) {
    lastFrameTime = millis();
    
    // Draw current frame
    const uint8_t* frameData = frames[currentFrame];
    
    for(int y = 0; y < PANEL_RES_Y; y++) {
      for(int x = 0; x < PANEL_RES_X; x++) {
        int idx = (y * PANEL_RES_X + x) * 3;
        uint8_t r = pgm_read_byte(&frameData[idx]);
        uint8_t g = pgm_read_byte(&frameData[idx + 1]);
        uint8_t b = pgm_read_byte(&frameData[idx + 2]);
        dma_display->drawPixelRGB888(x, y, r, g, b);
      }
    }
    
    dma_display->flipDMABuffer();
    
    // Next frame
    currentFrame = (currentFrame + 1) % NUM_FRAMES;
  }
}
"""
        else:
            code += """void loop() {
  // Draw static image
  for(int y = 0; y < PANEL_RES_Y; y++) {
    for(int x = 0; x < PANEL_RES_X; x++) {
      int idx = (y * PANEL_RES_X + x) * 3;
      uint8_t r = pgm_read_byte(&imageData[idx]);
      uint8_t g = pgm_read_byte(&imageData[idx + 1]);
      uint8_t b = pgm_read_byte(&imageData[idx + 2]);
      dma_display->drawPixelRGB888(x, y, r, g, b);
    }
  }
  
  dma_display->flipDMABuffer();
  delay(100);
}
"""
        
        return code

def main():
    try:
        print("=" * 60)
        print("LED Matrix Image/GIF Converter")
        print("=" * 60)
        
        converter = LEDMatrixConverter()
        
        # Get input file
        filepath = input("\nEnter image/GIF path: ").strip().strip('"\'')
        
        if not os.path.exists(filepath):
            print(f"Error: File '{filepath}' not found!")
            input("\nPress Enter to exit...")
            return
        
        # Check if GIF
        is_gif = filepath.lower().endswith('.gif')
        
        # Select effect
        print("\nAvailable effects:")
        print("1. None (original)")
        print("2. Pixelate")
        print("3. Neon (brightness boost)")
        print("4. Retro (reduced colors)")
        print("5. Edge detection")
        
        effect_choice = input("\nSelect effect (1-5): ").strip()
        effects = {
            '1': 'none',
            '2': 'pixelate',
            '3': 'neon',
            '4': 'retro',
            '5': 'edge'
        }
        effect = effects.get(effect_choice, 'none')
        
        # Load and process
        if is_gif:
            print("\nLoading GIF frames...")
            frames, durations = converter.load_gif_with_durations(filepath)
            print(f"Loaded {len(frames)} frames")
            
            if len(frames) > 30:
                print(f"⚠ Warning: {len(frames)} frames is a lot! This may not fit in ESP32 memory.")
                print("\nOptions:")
                print("1. Use every frame (may cause memory issues)")
                print("2. Use every 2nd frame")
                print("3. Use every 3rd frame")
                print("4. Cancel")
                
                choice = input("\nSelect option (1-4): ").strip()
                
                if choice == '4':
                    print("Cancelled.")
                    input("\nPress Enter to exit...")
                    return
                elif choice == '2':
                    frames = frames[::2]
                    durations = durations[::2]
                    print(f"Reduced to {len(frames)} frames")
                elif choice == '3':
                    frames = frames[::3]
                    durations = durations[::3]
                    print(f"Reduced to {len(frames)} frames")
                elif choice != '1':
                    print("Invalid choice. Cancelled.")
                    input("\nPress Enter to exit...")
                    return
            
            auto_delay = converter.calculate_frame_delay(durations)
            frame_delay_input = input(f"Frame delay in ms (default {auto_delay}): ").strip()
            frame_delay = int(frame_delay_input) if frame_delay_input else auto_delay
            
            print("\nGenerating Arduino code... This may take a minute...")
            code = converter.generate_arduino_code(frames, is_gif=True, effect=effect, frame_delay=frame_delay)
        else:
            print("\nLoading image...")
            image = converter.load_image(filepath)
            print("Generating Arduino code...")
            code = converter.generate_arduino_code(image, is_gif=False, effect=effect)
        
        # Save output
        output_file = input("\nEnter output filename (default: led_matrix_display.ino): ").strip()
        if not output_file:
            output_file = "led_matrix_display.ino"
        if not output_file.endswith('.ino'):
            output_file += '.ino'
        
        output_path = os.path.abspath(output_file)
        
        print(f"Writing file to: {output_path}")
        
        try:
            with open(output_file, 'w') as f:
                f.write(code)
        except PermissionError:
            # Try saving to Desktop as fallback
            desktop = os.path.join(os.path.expanduser("~"), "Desktop", output_file)
            print(f"Permission denied. Trying Desktop: {desktop}")
            with open(desktop, 'w') as f:
                f.write(code)
            output_path = desktop
        
        print(f"\n✓ Arduino code generated successfully!")
        print(f"📁 Saved to: {output_path}")
        print(f"\nEffect applied: {effect}")
        print("\nUpload this file to your ESP32 using Arduino IDE!")
        
    except Exception as e:
        print(f"\n❌ Error occurred: {str(e)}")
        import traceback
        traceback.print_exc()
    
    finally:
        input("\nPress Enter to exit...")


def web_main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--web", action="store_true")
    parser.add_argument("filepath")
    parser.add_argument("--effect", default="none")
    parser.add_argument("--width", type=int, default=64)
    parser.add_argument("--height", type=int, default=64)
    args = parser.parse_args()
    
    converter = LEDMatrixConverter(width=args.width, height=args.height)
    filepath = args.filepath
    effect = args.effect
    
    try:
        _im = Image.open(filepath)
        is_gif = (getattr(_im, "is_animated", False) or getattr(_im, "n_frames", 1) > 1 or (getattr(_im, "format", "") or "").upper() == "GIF")
    except Exception:
        is_gif = filepath.lower().endswith(".gif")
    
    # Conservative heap budget for ESP32 without PSRAM (WiFi + WebServer uses ~230 KB).
    # If the user has PSRAM enabled in Arduino IDE (Tools → PSRAM → Enabled) ps_malloc
    # works and hundreds of full-res frames fit — but we can't detect that from here,
    # so we auto-downscale for safety.
    HEAP_BUDGET   = 70 * 1024   # ~70 KB available for frame buffers without PSRAM
    MAX_WEB_FRAMES = 40

    try:
        if is_gif:
            frames, durations = converter.load_gif_with_durations(filepath)

            # Cap frame count first
            if len(frames) > MAX_WEB_FRAMES:
                step   = max(1, len(frames) // MAX_WEB_FRAMES)
                frames    = frames[::step]
                durations = durations[::step]

            # Auto-downscale resolution so frames fit in ESP32 heap without PSRAM.
            # 64×64×3 = 12 288 B/frame → ~5-6 frames max without PSRAM.
            # 32×32×3 =  3 072 B/frame → ~22 frames max without PSRAM.
            out_w, out_h = converter.width, converter.height
            n = len(frames)
            if n * out_w * out_h * 3 > HEAP_BUDGET:
                out_w, out_h = 32, 32
                resized = []
                for f in frames:
                    img = Image.fromarray(f.astype(np.uint8))
                    img = img.resize((out_w, out_h), Image.Resampling.NEAREST)
                    resized.append(np.array(img))
                frames = resized
                # Re-cap: at 32×32 budget allows ~22 frames
                max_at_small = HEAP_BUDGET // (out_w * out_h * 3)
                if n > max_at_small:
                    step   = max(1, n // max_at_small)
                    frames    = frames[::step]
                    durations = durations[::step]

            frame_delay = converter.calculate_frame_delay(durations)
            code = converter.generate_arduino_code(frames, is_gif=True, effect=effect, frame_delay=frame_delay)

            # Prepare frame data for web
            frames_data = []
            for frame in frames:
                frame_with_effect = converter.apply_effect(frame, effect)
                frames_data.append(converter.get_frame_data(frame_with_effect))

            result = {
                "status": "ok",
                "is_gif": True,
                "width": out_w,
                "height": out_h,
                "frame_count": len(frames),
                "frame_delay": frame_delay,
                "arduino_code": code,
                "frames": frames_data
            }
        else:
            image = converter.load_image(filepath)
            code = converter.generate_arduino_code(image, is_gif=False, effect=effect)
            
            # Prepare frame data
            image_with_effect = converter.apply_effect(image, effect)
            frame_data = converter.get_frame_data(image_with_effect)
            
            result = {
                "status": "ok",
                "is_gif": False,
                "width": converter.width,
                "height": converter.height,
                "frame_count": 1,
                "frame_delay": None,
                "arduino_code": code,
                "frames": [frame_data]
            }
        print(json.dumps(result))
    except Exception as e:
        error_result = {
            "status": "error",
            "message": str(e),
        }
        print(json.dumps(error_result))
        sys.exit(1)


if __name__ == "__main__":
    if "--web" in sys.argv:
        web_main()
    else:
        main()
