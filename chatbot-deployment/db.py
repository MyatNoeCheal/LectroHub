import pymysql

def get_db():
    """Connect to the same MySQL database your PHP site uses."""
    return pymysql.connect(
        host="localhost",
        user="root",              # change if your XAMPP MySQL user differs
        password="",               # change if you set a MySQL password
        database="lectrohub_db",   # match your actual DB name
        cursorclass=pymysql.cursors.DictCursor
    )