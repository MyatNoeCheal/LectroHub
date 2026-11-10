from difflib import get_close_matches
from db import get_db


def find_mentioned_product(message):
    """Fuzzy-match the user's message against product names in the DB."""
    db = get_db()
    with db.cursor() as cur:
        cur.execute("SELECT id, product_name, price, stock, image FROM products WHERE status='active'")
        products = cur.fetchall()
    db.close()

    names = [p['product_name'] for p in products]
    match = get_close_matches(message, names, n=1, cutoff=0.35)
    if match:
        return next(p for p in products if p['product_name'] == match[0])
    return None


def handle_product_recommendation(message, user_id=None):
    db = get_db()
    with db.cursor() as cur:
        cur.execute("""
            SELECT p.id, p.product_name AS name, p.price, p.image, p.stock
            FROM products p
            WHERE p.status = 'active' AND p.stock > 0
            ORDER BY p.sold DESC
            LIMIT 3
        """)
        rows = cur.fetchall()
    db.close()

    if not rows:
        return {"text": "I couldn't find any products to recommend right now."}

    return {
        "text": "Here are some popular picks:",
        "products": [
            {
                "name": r["name"],
                "price": float(r["price"]),
                "image": f"/LectroHub/admin/img/{r['image']}" if r["image"] else "https://placehold.co/200x200",
                "url": f"/LectroHub/customer/product.php?id={r['id']}"
            } for r in rows
        ]
    }


def handle_product_availability(message, user_id=None):
    product = find_mentioned_product(message)
    if not product:
        return {"text": "Which product did you mean? Could you tell me the name?"}

    if product["stock"] > 0:
        return {"text": f"Yes, \"{product['product_name']}\" is in stock ({product['stock']} available)."}
    return {"text": f"Sorry, \"{product['product_name']}\" is currently out of stock."}


def handle_price_inquiry(message, user_id=None):
    product = find_mentioned_product(message)
    if not product:
        return {"text": "Which product's price would you like to know?"}

    return {
        "text": f"\"{product['product_name']}\" is priced at €{float(product['price']):.2f}.",
        "products": [{
            "name": product["product_name"],
            "price": float(product["price"]),
            "image": f"/LectroHub/admin/img/{product['image']}" if product["image"] else "https://placehold.co/200x200",
            "url": f"/LectroHub/customer/product.php?id={product['id']}"
        }]
    }


def handle_order_status(message, user_id=None):
    if not user_id:
        return {"text": "Please log in to your account so I can check your order status."}

    db = get_db()
    with db.cursor() as cur:
        cur.execute("""
            SELECT id, status, total, created_at
            FROM orders
            WHERE user_id=%s
            ORDER BY id DESC
            LIMIT 1
        """, (user_id,))
        row = cur.fetchone()
    db.close()

    if not row:
        return {"text": "I couldn't find any orders on your account yet."}

    return {"text": f"Your most recent order (#{row['id']}) is currently **{row['status']}** — total €{float(row['total']):.2f}."}

def handle_order_items(message, user_id=None):
    if not user_id:
        return {"text": "Please log in to see your order details."}

    db = get_db()
    with db.cursor() as cur:
        cur.execute("SELECT id FROM orders WHERE user_id=%s ORDER BY id DESC LIMIT 1", (user_id,))
        order = cur.fetchone()
        if not order:
            db.close()
            return {"text": "You don't have any orders yet."}

        cur.execute("""
            SELECT product_name, quantity, price
            FROM order_items
            WHERE order_id=%s
        """, (order["id"],))
        items = cur.fetchall()
    db.close()

    if not items:
        return {"text": f"Order #{order['id']} has no items on record."}

    lines = [f"{i['quantity']}× {i['product_name']} (€{float(i['price']):.2f} each)" for i in items]
    return {"text": f"Order #{order['id']} contains:\n" + "\n".join(lines)}