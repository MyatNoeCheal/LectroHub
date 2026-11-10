import torch.nn as nn


class NeuralNet(nn.Module):
    """
    Simple feed-forward network for intent classification.
    input_size  -> size of the bag-of-words vector
    hidden_size -> size of the hidden layers
    num_classes -> number of intent tags
    """

    def __init__(self, input_size, hidden_size, num_classes):
        super(NeuralNet, self).__init__()
        self.l1 = nn.Linear(input_size, hidden_size)
        self.l2 = nn.Linear(hidden_size, hidden_size)
        self.l3 = nn.Linear(hidden_size, num_classes)
        self.relu = nn.ReLU()

    def forward(self, x):
        out = self.l1(x)
        out = self.relu(out)
        out = self.l2(out)
        out = self.relu(out)
        out = self.l3(out)
        # no activation/softmax here - CrossEntropyLoss applies it internally
        return out