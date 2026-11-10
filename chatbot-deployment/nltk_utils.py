import re

import numpy as np
from nltk.stem.porter import PorterStemmer

stemmer = PorterStemmer()

# Matches words (letters/numbers/apostrophes) or single punctuation marks.
# This avoids needing NLTK's 'punkt' downloader entirely, which some
# networks (school/office firewalls, offline machines) block outright.
_TOKEN_PATTERN = re.compile(r"[A-Za-z0-9']+|[.,!?;:]")


def tokenize(sentence):
    """
    Split a sentence into an array of words/tokens.
    e.g. "How are you?" -> ["How", "are", "you", "?"]
    No internet access or downloaded data required.
    """
    return _TOKEN_PATTERN.findall(sentence)


def stem(word):
    """
    Stemming = find the root form of the word.
    e.g. "organize", "organizes", "organizing" -> "organ"
    PorterStemmer is pure Python/rule-based and needs no downloads.
    """
    return stemmer.stem(word.lower())


def bag_of_words(tokenized_sentence, all_words):
    """
    Return a bag-of-words vector:
    1 for each word that exists in the sentence, 0 otherwise.
    """
    sentence_words = [stem(w) for w in tokenized_sentence]
    bag = np.zeros(len(all_words), dtype=np.float32)

    for idx, w in enumerate(all_words):
        if w in sentence_words:
            bag[idx] = 1.0

    return bag