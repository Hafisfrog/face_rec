from flask import Flask, request, jsonify
import os
import uuid
from recognize import recognize_face

app = Flask(__name__)

UPLOAD_DIR = "uploads"
os.makedirs(UPLOAD_DIR, exist_ok=True)

@app.route("/recognize", methods=["POST"])
def recognize():
    if "image" not in request.files:
        return jsonify({"error": "image is required"}), 400

    file = request.files["image"]
    filename = f"{uuid.uuid4()}.jpg"
    image_path = os.path.join(UPLOAD_DIR, filename)
    file.save(image_path)

    result = recognize_face(image_path)

    if result is None:
        return jsonify({"error": "recognition failed"}), 422

    return jsonify(result)

if __name__ == "__main__":
    app.run(port=5001, debug=True)
