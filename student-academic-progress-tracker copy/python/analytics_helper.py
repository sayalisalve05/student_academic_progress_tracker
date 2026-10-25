"""
Student Academic Tracker - optional Python analytics helper.
This script does not run the website; PHP/MySQL handle the application.
It can be used later for offline academic analysis.
"""

def percentage(obtained, maximum):
    if maximum == 0:
        return 0
    return round((obtained / maximum) * 100, 2)

def attendance_percentage(attended, total):
    if total == 0:
        return 0
    return round((attended / total) * 100, 2)

if __name__ == "__main__":
    print("Student Academic Tracker Python helper is ready.")
    print("Example marks:", percentage(85, 100), "%")
    print("Example attendance:", attendance_percentage(40, 45), "%")
