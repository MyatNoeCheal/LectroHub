import os

import pymysql


def get_db():
    """Connect to the same MySQL database the PHP site uses.

    Settings come from environment variables (see .env.example); the
    defaults match a local XAMPP install for development only.
    """
    return pymysql.connect(
        host=os.environ.get("DB_HOST", "localhost"),
        user=os.environ.get("DB_USER", "root"),
        password=os.environ.get("DB_PASSWORD", ""),
        database=os.environ.get("DB_NAME", "lectrohub_db"),
        cursorclass=pymysql.cursors.DictCursor,
    )
