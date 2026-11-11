import hmac
import os

from flask import Flask, request, jsonify
from flask_cors import CORS

from chat import get_response, bot_name

app = Flask(__name__)

# /predict trusts the user_id it receives, so only the LectroHub PHP server
# (which knows the real logged-in user) may call it. It proves itself with
# this shared secret.
_SHARED_SECRET = os.environ.get("CHATBOT_SHARED_SECRET", "").strip()
if not _SHARED_SECRET:
    raise RuntimeError("CHATBOT_SHARED_SECRET is not set; refusing to start.")
# Only the LectroHub site origin(s) may call the chatbot from a browser.
_origins = [o.strip() for o in os.environ.get(
    "CHATBOT_ALLOWED_ORIGINS", "http://localhost,http://127.0.0.1").split(",") if o.strip()]
CORS(app, origins=_origins)


@app.get("/")
def health():
    return jsonify({"status": "ok", "bot": bot_name})


@app.post("/predict")
def predict():
    supplied = request.headers.get("X-Chatbot-Secret", "")
    if not hmac.compare_digest(supplied, _SHARED_SECRET):
        return jsonify({"error": "Unauthorized"}), 401

    data = request.get_json(silent=True) or {}
    message = (data.get("message") or "").strip()
    user_id = data.get("user_id")

    if not message:
        return jsonify({"error": "No message provided"}), 400

    result = get_response(message, user_id=user_id)
    return jsonify(result)


if __name__ == "__main__":
    # Debug mode exposes an interactive code-execution console, so it is OFF
    # unless FLASK_DEBUG=1 is set explicitly, and the server only listens on
    # localhost by default.
    app.run(
        host=os.environ.get("CHATBOT_HOST", "127.0.0.1"),
        port=int(os.environ.get("CHATBOT_PORT", "5000")),
        debug=os.environ.get("FLASK_DEBUG") == "1",
    )
