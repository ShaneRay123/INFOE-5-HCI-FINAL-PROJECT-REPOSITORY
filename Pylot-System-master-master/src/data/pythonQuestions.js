export const pythonQuestions = {
  beginner: [
    {
      id: 1,
      question: "What is the correct way to create a variable in Python?",
      options: [
        "var name = 'John'",
        "name = 'John'",
        "variable name = 'John'",
        "name := 'John'"
      ],
      correct: 1,
      explanation: "In Python, variables are created by simply assigning a value using the = operator. No declaration is needed."
    },
    {
      id: 2,
      question: "Which of the following is the correct way to print 'Hello World' in Python?",
      options: [
        "print('Hello World')",
        "echo 'Hello World'",
        "console.log('Hello World')",
        "System.out.println('Hello World')"
      ],
      correct: 0,
      explanation: "The print() function is used to display output in Python."
    },
    {
      id: 3,
      question: "What data type is the value 42 in Python?",
      options: [
        "string",
        "float",
        "integer",
        "boolean"
      ],
      correct: 2,
      explanation: "42 is an integer (int) data type in Python."
    },
    {
      id: 4,
      question: "Which operator is used for exponentiation in Python?",
      options: [
        "^",
        "**",
        "pow()",
        "exp()"
      ],
      correct: 1,
      explanation: "The ** operator is used for exponentiation in Python (e.g., 2**3 = 8)."
    },
    {
      id: 5,
      question: "What will be the output of: print(type(3.14))",
      options: [
        "<class 'int'>",
        "<class 'float'>",
        "<class 'double'>",
        "<class 'number'>"
      ],
      correct: 1,
      explanation: "3.14 is a float data type in Python."
    },
    {
      id: 6,
      question: "How do you create a list in Python?",
      options: [
        "list = []",
        "list = {}",
        "list = ()",
        "list = <>"
      ],
      correct: 0,
      explanation: "Lists are created using square brackets [] in Python."
    },
    {
      id: 7,
      question: "What is the result of: 10 / 3",
      options: [
        "3",
        "3.3333333333333335",
        "3.0",
        "Error"
      ],
      correct: 1,
      explanation: "Division with / always returns a float in Python 3."
    },
    {
      id: 8,
      question: "Which keyword is used to define a function in Python?",
      options: [
        "function",
        "def",
        "func",
        "define"
      ],
      correct: 1,
      explanation: "The 'def' keyword is used to define functions in Python."
    },
    {
      id: 9,
      question: "What will be the output of: print('Python'[0])",
      options: [
        "P",
        "p",
        "Python",
        "Error"
      ],
      correct: 0,
      explanation: "String indexing starts at 0, so [0] returns the first character 'P'."
    },
    {
      id: 10,
      question: "Which of the following is a valid Python comment?",
      options: [
        "// This is a comment",
        "",
        "# This is a comment",
        "<!-- This is a comment -->"
      ],
      correct: 2,
      explanation: "In Python, comments start with the # symbol."
    }
  ],
  intermediate: [
    {
      id: 11,
      question: "What will be the output of: [1, 2, 3] + [4, 5, 6]",
      options: [
        "[1, 2, 3, 4, 5, 6]",
        "[5, 7, 9]",
        "Error",
        "[1, 2, 3][4, 5, 6]"
      ],
      correct: 0,
      explanation: "The + operator concatenates lists in Python."
    },
    {
      id: 12,
      question: "What does the 'in' operator do when used with lists?",
      options: [
        "Adds an element to the list",
        "Checks if an element exists in the list",
        "Removes an element from the list",
        "Sorts the list"
      ],
      correct: 1,
      explanation: "The 'in' operator checks membership, returning True if the element exists in the list."
    },
    {
      id: 13,
      question: "What will be the output of: len('Python Programming')",
      options: [
        "16",
        "17",
        "18",
        "19"
      ],
      correct: 2,
      explanation: "The len() function returns the number of characters, including spaces."
    },
    {
      id: 14,
      question: "Which method is used to add an element to the end of a list?",
      options: [
        "append()",
        "add()",
        "insert()",
        "extend()"
      ],
      correct: 0,
      explanation: "The append() method adds a single element to the end of a list."
    },
    {
      id: 15,
      question: "What will be the output of: 'Hello'.upper()",
      options: [
        "hello",
        "HELLO",
        "Hello",
        "Error"
      ],
      correct: 1,
      explanation: "The upper() method converts all characters to uppercase."
    },
    {
      id: 16,
      question: "What is the result of: 2 ** 3 ** 2",
      options: [
        "64",
        "512",
        "36",
        "Error"
      ],
      correct: 1,
      explanation: "Exponentiation is right-associative, so 2 ** 3 ** 2 = 2 ** (3 ** 2) = 2 ** 9 = 512."
    },
    {
      id: 17,
      question: "Which of the following creates a dictionary in Python?",
      options: [
        "dict = []",
        "dict = {}",
        "dict = ()",
        "dict = <>"
      ],
      correct: 1,
      explanation: "Dictionaries are created using curly braces {} in Python."
    },
    {
      id: 18,
      question: "What will be the output of: range(5)",
      options: [
        "[0, 1, 2, 3, 4]",
        "[1, 2, 3, 4, 5]",
        "range(0, 5)",
        "Error"
      ],
      correct: 2,
      explanation: "range(5) returns a range object, not a list. To get a list, use list(range(5))."
    },
    {
      id: 19,
      question: "What does the 'strip()' method do?",
      options: [
        "Removes whitespace from both ends",
        "Adds whitespace to both ends",
        "Reverses the string",
        "Converts to lowercase"
      ],
      correct: 0,
      explanation: "The strip() method removes leading and trailing whitespace characters."
    },
    {
      id: 20,
      question: "What will be the output of: [1, 2, 3] * 2",
      options: [
        "[2, 4, 6]",
        "[1, 2, 3, 1, 2, 3]",
        "Error",
        "[1, 1, 2, 2, 3, 3]"
      ],
      correct: 1,
      explanation: "The * operator repeats the list the specified number of times."
    },
    {
      id: 21,
      question: "Which of the following is the correct way to create a tuple?",
      options: [
        "tuple = [1, 2, 3]",
        "tuple = (1, 2, 3)",
        "tuple = {1, 2, 3}",
        "tuple = 1, 2, 3"
      ],
      correct: 1,
      explanation: "Tuples are created using parentheses () in Python."
    },
    {
      id: 22,
      question: "What will be the output of: 'Python'.replace('P', 'J')",
      options: [
        "Jython",
        "Python",
        "Jython",
        "Error"
      ],
      correct: 0,
      explanation: "The replace() method replaces all occurrences of the first argument with the second."
    },
    {
      id: 23,
      question: "Which method is used to find the index of an element in a list?",
      options: [
        "find()",
        "index()",
        "search()",
        "locate()"
      ],
      correct: 1,
      explanation: "The index() method returns the index of the first occurrence of the specified element."
    },
    {
      id: 24,
      question: "What will be the output of: sorted([3, 1, 4, 1, 5])",
      options: [
        "[3, 1, 4, 1, 5]",
        "[1, 1, 3, 4, 5]",
        "[5, 4, 3, 1, 1]",
        "Error"
      ],
      correct: 1,
      explanation: "The sorted() function returns a new sorted list in ascending order."
    },
    {
      id: 25,
      question: "What does the 'join()' method do?",
      options: [
        "Splits a string into a list",
        "Joins elements of a list into a string",
        "Adds elements to a list",
        "Removes elements from a list"
      ],
      correct: 1,
      explanation: "The join() method concatenates elements of a list with a specified separator."
    },
    {
      id: 26,
      question: "What will be the output of: [x for x in range(5) if x % 2 == 0]",
      options: [
        "[0, 2, 4]",
        "[1, 3]",
        "[0, 1, 2, 3, 4]",
        "Error"
      ],
      correct: 0,
      explanation: "This is a list comprehension that creates a list of even numbers from 0 to 4."
    },
    {
      id: 27,
      question: "Which of the following is a valid way to create a set?",
      options: [
        "set = [1, 2, 3]",
        "set = {1, 2, 3}",
        "set = (1, 2, 3)",
        "set = 1, 2, 3"
      ],
      correct: 1,
      explanation: "Sets are created using curly braces {} in Python."
    },
    {
      id: 28,
      question: "What will be the output of: 'Hello World'.split()",
      options: [
        "['Hello', 'World']",
        "['H', 'e', 'l', 'l', 'o', ' ', 'W', 'o', 'r', 'l', 'd']",
        "Error",
        "Hello World"
      ],
      correct: 0,
      explanation: "The split() method without arguments splits on whitespace and returns a list of words."
    },
    {
      id: 29,
      question: "Which operator is used for floor division in Python?",
      options: [
        "/",
        "//",
        "%",
        "div"
      ],
      correct: 1,
      explanation: "The // operator performs floor division, returning the largest integer less than or equal to the division result."
    },
    {
      id: 30,
      question: "What will be the output of: max([1, 5, 3, 9, 2])",
      options: [
        "1",
        "5",
        "9",
        "Error"
      ],
      correct: 2,
      explanation: "The max() function returns the largest element in the iterable."
    }
  ]
};

export const assessmentConfig = {
  beginner: {
    title: "Python Basics Assessment",
    description: "Test your fundamental Python knowledge",
    timeLimit: 10,
    totalQuestions: 10,
    passingScore: 70
  },
  intermediate: {
    title: "Python Intermediate Assessment", 
    description: "Test your intermediate Python programming skills",
    timeLimit: 20,
    totalQuestions: 20,
    passingScore: 75
  }
};
