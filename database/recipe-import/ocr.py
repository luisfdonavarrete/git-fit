import sys, Vision, Quartz
from Foundation import NSURL
def ocr(path):
    url = NSURL.fileURLWithPath_(path)
    src = Quartz.CGImageSourceCreateWithURL(url, None)
    img = Quartz.CGImageSourceCreateImageAtIndex(src, 0, None)
    req = Vision.VNRecognizeTextRequest.alloc().init()
    req.setRecognitionLevel_(Vision.VNRequestTextRecognitionLevelAccurate)
    req.setUsesLanguageCorrection_(False)
    handler = Vision.VNImageRequestHandler.alloc().initWithCGImage_options_(img, None)
    handler.performRequests_error_([req], None)
    out = []
    for r in req.results():
        c = r.topCandidates_(1)[0]
        b = r.boundingBox()  # normalized, origin bottom-left
        out.append((round(b.origin.x, 3), round(b.origin.y, 3), round(b.size.width, 3), c.string()))
    return out
if __name__ == '__main__':
    for row in ocr(sys.argv[1]): print(row)
