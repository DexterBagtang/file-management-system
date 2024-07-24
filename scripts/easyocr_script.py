import sys
import easyocr

image_path = sys.argv[1]
reader = easyocr.Reader(['en'])
result = reader.readtext(image_path)

text = ""
for detection in result:
    text += detection[1] + "\n"

print(text)
