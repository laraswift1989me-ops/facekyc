import sys
import json
import os
import cv2
import dlib
import numpy as np
import pytesseract

# Dynamic paths to the models (works regardless of project directory name)
_SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
_PROJECT_DIR = os.path.dirname(_SCRIPT_DIR)
MODEL_DIR = os.path.join(_PROJECT_DIR, "kyc_env", "lib", "python3.12", "site-packages", "face_recognition_models", "models") + os.sep
SHAPE_PREDICTOR = os.path.join(MODEL_DIR, "shape_predictor_68_face_landmarks.dat")
FACE_RECOG = os.path.join(MODEL_DIR, "dlib_face_recognition_resnet_model_v1.dat")

def get_encoding(img_rgb, detector, sp, facerec):
    # Upsample once (1) to find smaller faces on ID cards
    dets = detector(img_rgb, 1)
    if len(dets) == 0:
        return None
    shape = sp(img_rgb, dets[0])
    return np.array(facerec.compute_face_descriptor(img_rgb, shape))

def extract_text(image_path):
    try:
        img = cv2.imread(image_path)
        if img is None: return ""
        # Convert to grayscale and threshold to improve OCR accuracy
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        gray = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY | cv2.THRESH_OTSU)[1]
        text = pytesseract.image_to_string(gray)
        return text.strip()
    except:
        return ""

def process_kyc(doc_path, video_path):
    try:
        # Initialize dlib engines
        detector = dlib.get_frontal_face_detector()
        sp = dlib.shape_predictor(SHAPE_PREDICTOR)
        facerec = dlib.face_recognition_model_v1(FACE_RECOG)

        # 1. OCR: Extract Name from ID
        id_text = extract_text(doc_path)

        # 2. Process ID Face
        doc_img = cv2.imread(doc_path)
        if doc_img is None:
            return {"status": "failed", "feedback": "Invalid ID image file."}
        doc_rgb = cv2.cvtColor(doc_img, cv2.COLOR_BGR2RGB)
        doc_enc = get_encoding(doc_rgb, detector, sp, facerec)

        if doc_enc is None:
            return {"status": "failed", "feedback": "No face found on ID document.", "ocr_text": id_text}

        # 3. Process Video Selfie (Grab first clear frame)
        cap = cv2.VideoCapture(video_path)
        success, frame = cap.read()
        cap.release()

        if not success:
            return {"status": "failed", "feedback": "Could not read video file.", "ocr_text": id_text}

        vid_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        vid_enc = get_encoding(vid_rgb, detector, sp, facerec)

        if vid_enc is None:
            return {"status": "failed", "feedback": "No face detected in video selfie.", "ocr_text": id_text}

        # 4. Compare Faces (Euclidean Distance)
        distance = np.linalg.norm(doc_enc - vid_enc)
        
        # Match Threshold: 0.55 is a good balance for AI
        match = bool(distance <= 0.55)
        confidence = round((1.0 - distance) * 100, 2)

        return {
            "status": "approved" if match else "failed",
            "face_match": match,
            "confidence": confidence,
            "ocr_text": id_text,
            "feedback": "Face match verified." if match else "Face mismatch."
        }

    except Exception as e:
        return {"status": "error", "feedback": str(e)}

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"status": "error", "feedback": "Missing arguments."}))
    else:
        # Final output is pure JSON for Laravel to parse
        result = process_kyc(sys.argv[1], sys.argv[2])
        print(json.dumps(result))