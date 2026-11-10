from flask import Flask, request, jsonify
from flask_cors import CORS

from chat import get_response, bot_name

app = Flask(__name__)
CORS(app)


@app.get("/")
def health():
    return jsonify({"status": "ok", "bot": bot_name})


@app.post("/predict")
def predict():
    data = request.get_json(silent=True) or {}
    message = (data.get("message") or "").strip()
    user_id = data.get("user_id")

    if not message:
        return jsonify({"error": "No message provided"}), 400

    result = get_response(message, user_id=user_id)
    return jsonify(result)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)