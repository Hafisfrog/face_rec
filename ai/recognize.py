import cv2
import numpy as np
import os
import json
import sys


def read_image(path):
    if not os.path.exists(path):
        return None

    data = np.fromfile(path, dtype=np.uint8)
    img = cv2.imdecode(data, cv2.IMREAD_COLOR)
    return img


# ✅ ฟังก์ชันนี้ไว้ให้ Flask import
def recognize_face(image_path):
    img = read_image(image_path)

    if img is None:
        return None

    # mock score (AI จริงค่อยแทนตรงนี้)
    score = 0.92

    return {
        "user_id": "123",
        "score": score,
        "decision": "allow" if score > 0.85 else "review"
    }


# ✅ CLI mode (ยังใช้ได้เหมือนเดิม)
def main(image_path):
    result = recognize_face(image_path)

    if result is None:
        print(json.dumps({
            "success": False,
            "error": "Image not found or invalid"
        }))
        return

    print(json.dumps({
        "success": True,
        **result
    }))


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({
            "success": False,
            "error": "Image path not provided"
        }))
        sys.exit(1)

    image_path = sys.argv[1]
    main(image_path)
