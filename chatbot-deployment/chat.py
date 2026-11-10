import random
import json
import sys

import torch

from model import NeuralNet
from nltk_utils import bag_of_words, tokenize


from handlers import (
    handle_product_recommendation,
    handle_product_availability,
    handle_price_inquiry,
    handle_order_status,
)

DYNAMIC_HANDLERS = {
    "product_recommendation": handle_product_recommendation,
    "product_availability": handle_product_availability,
    "price_inquiry": handle_price_inquiry,
    "order_status": handle_order_status,
}

# Force UTF-8 output so responses print correctly on Windows terminals
# regardless of the console's default codepage.
try:
    sys.stdout.reconfigure(encoding='utf-8')
except AttributeError:
    pass  # older Python versions without reconfigure(); safe to ignore

device = torch.device('cuda' if torch.cuda.is_available() else 'cpu')

with open('intents.json', 'r', encoding='utf-8') as f:
    intents = json.load(f)

FILE = "data.pth"
data = torch.load(FILE, map_location=device)

input_size = data["input_size"]
hidden_size = data["hidden_size"]
output_size = data["output_size"]
all_words = data['all_words']
tags = data['tags']
model_state = data["model_state"]

model = NeuralNet(input_size, hidden_size, output_size).to(device)
model.load_state_dict(model_state)
model.eval()

bot_name = "LectroBot"
CONFIDENCE_THRESHOLD = 0.75
FALLBACK_RESPONSE = (
    "I'm not totally sure I understood that. Could you rephrase, "
    "or ask about orders, shipping, returns, or products?"
)


def get_response(message: str, user_id=None) -> dict:
    """
    Takes a raw user message, returns a dict:
    {"text": "...", "products": [...]}  (products key optional)
    This is the function app.py imports and calls.
    """
    tokens = tokenize(message)
    X = bag_of_words(tokens, all_words)
    X = X.reshape(1, X.shape[0])
    X = torch.from_numpy(X).to(device)

    with torch.no_grad():
        output = model(X)
        probs = torch.softmax(output, dim=1)
        prob, predicted = torch.max(probs, dim=1)

    tag = tags[predicted.item()]

    if prob.item() > CONFIDENCE_THRESHOLD:
        if tag in DYNAMIC_HANDLERS:
            return DYNAMIC_HANDLERS[tag](message, user_id)

        for intent in intents['intents']:
            if tag == intent["tag"]:
                return {"text": random.choice(intent['responses'])}

    return {"text": FALLBACK_RESPONSE}


if __name__ == "__main__":
    print(f"{bot_name} is online. Type 'quit' to exit.")
    while True:
        sentence = input('You: ')
        if sentence.lower() == 'quit':
            break
        result = get_response(sentence)
        print(f"{bot_name}: {result['text']}")