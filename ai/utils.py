import cv2
import numpy as np
import os
from numpy.linalg import norm

def read_image(path):
    if not os.path.exists(path):
        return None

    data = np.fromfile(path, dtype=np.uint8)
    img = cv2.imdecode(data, cv2.IMREAD_COLOR)
    return img

def get_embedding(img):
    img = cv2.resize(img, (100, 100))
    hist = cv2.calcHist([img], [0,1,2], None, [8,8,8], [0,256]*3)
    hist = cv2.normalize(hist, hist).flatten()
    return hist

def cosine_similarity(a, b):
    return float(a @ b / (norm(a) * norm(b)))
