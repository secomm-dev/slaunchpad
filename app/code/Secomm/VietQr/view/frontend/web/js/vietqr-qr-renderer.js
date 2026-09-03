/**
 * VietQR QR Code Renderer
 * Renders EMVCo QR string to SVG using qrcode-generator library.
 */
(function () {
    'use strict';

    var qrcodeGenerator = (function () {
        var QRMode = {
            MODE_NUMBER: 1 << 0,
            MODE_ALPHA_NUM: 1 << 1,
            MODE_8BIT_BYTE: 1 << 2,
            MODE_KANJI: 1 << 3
        };

        var QRErrorCorrectLevel = {
            L: 1,
            M: 0,
            Q: 3,
            H: 2
        };

        var QRMaskPattern = {
            PATTERN000: 0,
            PATTERN001: 1,
            PATTERN010: 2,
            PATTERN011: 3,
            PATTERN100: 4,
            PATTERN101: 5,
            PATTERN110: 6,
            PATTERN111: 7
        };

        var QRUtil = {
            PATTERN_POSITION_TABLE: [
                [],
                [6, 18],
                [6, 22],
                [6, 26],
                [6, 30],
                [6, 34],
                [6, 22, 38],
                [6, 24, 42],
                [6, 26, 46],
                [6, 28, 50],
                [6, 30, 54],
                [6, 32, 58],
                [6, 34, 62],
                [6, 26, 46, 66],
                [6, 26, 48, 70],
                [6, 26, 50, 74],
                [6, 30, 54, 78],
                [6, 30, 56, 82],
                [6, 30, 58, 86],
                [6, 34, 62, 90],
                [6, 28, 50, 72, 94],
                [6, 26, 50, 74, 98],
                [6, 30, 54, 78, 102],
                [6, 28, 54, 80, 106],
                [6, 32, 58, 84, 110],
                [6, 30, 58, 86, 114],
                [6, 34, 62, 90, 118],
                [6, 26, 50, 74, 98, 122],
                [6, 30, 54, 78, 102, 126],
                [6, 26, 52, 78, 104, 130],
                [6, 30, 56, 82, 108, 134],
                [6, 34, 60, 86, 112, 138],
                [6, 30, 58, 86, 114, 142],
                [6, 34, 62, 90, 118, 146],
                [6, 30, 54, 78, 102, 126, 150],
                [6, 24, 50, 76, 102, 128, 154],
                [6, 28, 54, 80, 106, 132, 158],
                [6, 32, 58, 84, 110, 136, 162],
                [6, 26, 54, 82, 110, 138, 166],
                [6, 30, 58, 86, 114, 142, 170]
            ],
            G15: (1 << 10) | (1 << 8) | (1 << 5) | (1 << 4) | (1 << 2) | (1 << 1) | (1 << 0),
            G18: (1 << 14) | (1 << 12) | (1 << 10) | (1 << 4) | (1 << 1),
            G15_MASK: (1 << 14) | (1 << 12) | (1 << 10) | (1 << 4) | (1 << 1),
            getBCHTypeInfo: function (data) {
                var d = data << 10;
                while (QRUtil.getBCHDigit(d) - QRUtil.getBCHDigit(QRUtil.G15) >= 0) {
                    d ^= (QRUtil.G15 << (QRUtil.getBCHDigit(d) - QRUtil.getBCHDigit(QRUtil.G15)));
                }
                return ((data << 10) | d) ^ QRUtil.G15_MASK;
            },
            getBCHTypeNumber: function (data) {
                var d = data << 12;
                while (QRUtil.getBCHDigit(d) - QRUtil.getBCHDigit(QRUtil.G18) >= 0) {
                    d ^= (QRUtil.G18 << (QRUtil.getBCHDigit(d) - QRUtil.getBCHDigit(QRUtil.G18)));
                }
                return (data << 12) | d;
            },
            getBCHDigit: function (data) {
                var digit = 0;
                while (data != 0) {
                    digit++;
                    data >>>= 1;
                }
                return digit;
            },
            getPatternPosition: function (typeNumber) {
                return QRUtil.PATTERN_POSITION_TABLE[typeNumber - 1];
            },
            getMask: function (maskPattern, i, j) {
                switch (maskPattern) {
                    case QRMaskPattern.PATTERN000: return (i + j) % 2 == 0;
                    case QRMaskPattern.PATTERN001: return i % 2 == 0;
                    case QRMaskPattern.PATTERN010: return j % 3 == 0;
                    case QRMaskPattern.PATTERN011: return (i + j) % 3 == 0;
                    case QRMaskPattern.PATTERN100: return (Math.floor(i / 2) + Math.floor(j / 3)) % 2 == 0;
                    case QRMaskPattern.PATTERN101: return (i * j) % 2 + (i * j) % 3 == 0;
                    case QRMaskPattern.PATTERN110: return ((i * j) % 2 + (i * j) % 3) % 2 == 0;
                    case QRMaskPattern.PATTERN111: return ((i * j) % 3 + (i + j) % 2) % 2 == 0;
                    default: throw new Error("bad maskPattern:" + maskPattern);
                }
            },
            getErrorCorrectPolynomial: function (errorCorrectLength) {
                var a = new QRPolynomial([1], 0);
                for (var i = 0; i < errorCorrectLength; i++) {
                    a = a.multiply(new QRPolynomial([1, QRMath.gexp(i)], 0));
                }
                return a;
            },
            getLengthInBits: function (mode, type) {
                if (1 <= type && type < 10) {
                    switch (mode) {
                        case QRMode.MODE_NUMBER: return 10;
                        case QRMode.MODE_ALPHA_NUM: return 9;
                        case QRMode.MODE_8BIT_BYTE: return 8;
                        case QRMode.MODE_KANJI: return 8;
                        default: throw new Error("mode:" + mode);
                    }
                } else if (type < 27) {
                    switch (mode) {
                        case QRMode.MODE_NUMBER: return 12;
                        case QRMode.MODE_ALPHA_NUM: return 11;
                        case QRMode.MODE_8BIT_BYTE: return 16;
                        case QRMode.MODE_KANJI: return 10;
                        default: throw new Error("mode:" + mode);
                    }
                } else if (type < 41) {
                    switch (mode) {
                        case QRMode.MODE_NUMBER: return 14;
                        case QRMode.MODE_ALPHA_NUM: return 13;
                        case QRMode.MODE_8BIT_BYTE: return 16;
                        case QRMode.MODE_KANJI: return 12;
                        default: throw new Error("mode:" + mode);
                    }
                } else {
                    throw new Error("type:" + type);
                }
            },
            getLostPoint: function (qrCode) {
                var moduleCount = qrCode.getModuleCount();
                var lostPoint = 0;
                for (var row = 0; row < moduleCount; row++) {
                    for (var col = 0; col < moduleCount; col++) {
                        var sameCount = 0;
                        var dark = qrCode.isDark(row, col);
                        for (var r = -1; r <= 1; r++) {
                            if (row + r < 0 || moduleCount <= row + r) continue;
                            for (var c = -1; c <= 1; c++) {
                                if (col + c < 0 || moduleCount <= col + c) continue;
                                if (r == 0 && c == 0) continue;
                                if (dark == qrCode.isDark(row + r, col + c)) {
                                    sameCount++;
                                }
                            }
                        }
                        if (sameCount > 5) {
                            lostPoint += (3 + sameCount - 5);
                        }
                    }
                }
                for (var row = 0; row < moduleCount - 1; row++) {
                    for (var col = 0; col < moduleCount - 1; col++) {
                        var count = 0;
                        if (qrCode.isDark(row, col)) count++;
                        if (qrCode.isDark(row + 1, col)) count++;
                        if (qrCode.isDark(row, col + 1)) count++;
                        if (qrCode.isDark(row + 1, col + 1)) count++;
                        if (count == 0 || count == 4) {
                            lostPoint += 3;
                        }
                    }
                }
                for (var row = 0; row < moduleCount; row++) {
                    for (var col = 0; col < moduleCount - 6; col++) {
                        if (qrCode.isDark(row, col) && !qrCode.isDark(row, col + 1) && qrCode.isDark(row, col + 2) && qrCode.isDark(row, col + 3) && qrCode.isDark(row, col + 4) && !qrCode.isDark(row, col + 5) && qrCode.isDark(row, col + 6)) {
                            lostPoint += 40;
                        }
                    }
                }
                for (var col = 0; col < moduleCount; col++) {
                    for (var row = 0; row < moduleCount - 6; row++) {
                        if (qrCode.isDark(row, col) && !qrCode.isDark(row + 1, col) && qrCode.isDark(row + 2, col) && qrCode.isDark(row + 3, col) && qrCode.isDark(row + 4, col) && !qrCode.isDark(row + 5, col) && qrCode.isDark(row + 6, col)) {
                            lostPoint += 40;
                        }
                    }
                }
                var darkCount = 0;
                for (var col = 0; col < moduleCount; col++) {
                    for (var row = 0; row < moduleCount; row++) {
                        if (qrCode.isDark(row, col)) {
                            darkCount++;
                        }
                    }
                }
                var ratio = Math.abs(100 * darkCount / moduleCount / moduleCount - 50) / 5;
                lostPoint += ratio * 10;
                return lostPoint;
            }
        };

        var QRMath = {
            glog: function (n) {
                if (n < 1) throw new Error("glog(" + n + ")");
                return QRMath.LOG_TABLE[n];
            },
            gexp: function (n) {
                while (n < 0) { n += 255; }
                while (n >= 256) { n -= 255; }
                return QRMath.EXP_TABLE[n];
            },
            EXP_TABLE: new Array(256),
            LOG_TABLE: new Array(256)
        };
        for (var i = 0; i < 8; i++) { QRMath.EXP_TABLE[i] = 1 << i; }
        for (var i = 8; i < 256; i++) { QRMath.EXP_TABLE[i] = QRMath.EXP_TABLE[i - 4] ^ QRMath.EXP_TABLE[i - 5] ^ QRMath.EXP_TABLE[i - 6] ^ QRMath.EXP_TABLE[i - 8]; }
        for (var i = 0; i < 255; i++) { QRMath.LOG_TABLE[QRMath.EXP_TABLE[i]] = i; }

        function QRPolynomial(num, shift) {
            if (num.length == undefined) throw new Error(num.length + "/" + shift);
            var offset = 0;
            while (offset < num.length && num[offset] == 0) offset++;
            this.num = new Array(num.length - offset + shift);
            for (var i = 0; i < num.length - offset; i++) this.num[i] = num[i + offset];
        }
        QRPolynomial.prototype = {
            get: function (index) { return this.num[index]; },
            getLength: function () { return this.num.length; },
            multiply: function (e) {
                var num = new Array(this.getLength() + e.getLength() - 1);
                for (var i = 0; i < this.getLength(); i++) {
                    for (var j = 0; j < e.getLength(); j++) {
                        num[i + j] ^= QRMath.gexp(QRMath.glog(this.get(i)) + QRMath.glog(e.get(j)));
                    }
                }
                return new QRPolynomial(num, 0);
            },
            mod: function (e) {
                if (this.getLength() - e.getLength() < 0) return this;
                var ratio = QRMath.glog(this.get(0)) - QRMath.glog(e.get(0));
                var num = new Array(this.getLength());
                for (var i = 0; i < this.getLength(); i++) num[i] = this.get(i);
                for (var i = 0; i < e.getLength(); i++) num[i] ^= QRMath.gexp(QRMath.glog(e.get(i)) + ratio);
                return new QRPolynomial(num, 0).mod(e);
            }
        };

        var QRRSBlock = {
            getRSBlocks: function (typeNumber, errorCorrectionLevel) {
                var rsBlock = QRRSBlock.getRsBlockTable(typeNumber, errorCorrectionLevel);
                if (rsBlock == undefined) throw new Error("bad rs block @ typeNumber:" + typeNumber + "/errorCorrectionLevel:" + errorCorrectionLevel);
                var length = rsBlock.length / 3;
                var list = [];
                for (var i = 0; i < length; i++) {
                    var count = rsBlock[i * 3 + 0];
                    var totalCount = rsBlock[i * 3 + 1];
                    var dataCount = rsBlock[i * 3 + 2];
                    for (var j = 0; j < count; j++) {
                        list.push({ totalCount: totalCount, dataCount: dataCount });
                    }
                }
                return list;
            },
            getRsBlockTable: function (typeNumber, errorCorrectionLevel) {
                switch (errorCorrectionLevel) {
                    case QRErrorCorrectLevel.L:
                        switch (typeNumber) {
                            case 1: return [1,26,19];case 2: return [1,44,34];case 3: return [1,70,55];case 4: return [1,100,80];case 5: return [1,134,108];case 6: return [2,86,68];case 7: return [2,98,78];case 8: return [2,119,97];case 9: return [2,154,122];case 10: return [2,182,140];case 11: return [3,158,118];case 12: return [3,180,138];case 13: return [4,176,134];case 14: return [4,216,160];case 15: return [4,240,174];case 16: return [4,280,200];case 17: return [4,320,224];case 18: return [4,368,246];case 19: return [4,418,280];case 20: return [4,472,310];case 21: return [4,528,338];case 22: return [4,588,382];case 23: return [4,650,403];case 24: return [4,712,439];case 25: return [4,778,461];case 26: return [4,848,511];case 27: return [4,924,535];case 28: return [4,1000,573];case 29: return [4,1072,595];case 30: return [4,1156,625];case 31: return [4,1228,658];case 32: return [4,1308,698];case 33: return [4,1392,742];case 34: return [4,1476,790];case 35: return [4,1564,842];case 36: return [4,1656,898];case 37: return [4,1752,958];case 38: return [4,1844,1018];case 39: return [4,1940,1082];case 40: return [4,2040,1102];
                        }
                        break;
                    case QRErrorCorrectLevel.M:
                        switch (typeNumber) {
                            case 1: return [1,26,16];case 2: return [1,44,28];case 3: return [1,70,44];case 4: return [2,50,32];case 5: return [2,67,43];case 6: return [4,43,27];case 7: return [4,49,31];case 8: return [2,60,38,2,61,39];case 9: return [3,58,36,2,59,37];case 10: return [4,69,43,1,70,44];case 11: return [1,80,50,4,81,51];case 12: return [6,58,36,2,59,37];case 13: return [8,59,37,4,60,38];case 14: return [11,54,34,5,55,35];case 15: return [5,78,49,7,79,50];case 16: return [15,56,35,2,57,36];case 17: return [1,101,64,15,102,65];case 18: return [2,75,47,17,76,48];case 19: return [2,91,57,19,92,58];case 20: return [4,89,56,22,90,57];case 21: return [6,95,60,22,96,61];case 22: return [8,89,56,24,90,57];case 23: return [4,98,62,28,99,63];case 24: return [13,87,55,23,88,56];case 25: return [17,98,62,19,99,63];case 26: return [17,98,62,21,99,63];case 27: return [19,99,63,21,100,64];case 28: return [18,100,64,26,101,65];case 29: return [22,100,64,26,101,65];case 30: return [22,101,65,28,102,66];case 31: return [22,102,66,33,103,67];case 32: return [26,103,67,32,104,68];case 33: return [30,104,68,29,105,69];case 34: return [34,104,68,34,105,69];case 35: return [14,106,70,46,107,71];case 36: return [35,106,70,39,107,71];case 37: return [19,108,72,61,109,73];case 38: return [17,110,74,68,111,75];case 39: return [20,110,74,74,111,75];case 40: return [18,112,76,82,113,77];
                        }
                        break;
                }
                return undefined;
            }
        };

        function QRBitBuffer() {
            this.buffer = [];
            this.length = 0;
        }
        QRBitBuffer.prototype = {
            get: function (index) {
                var bufIndex = Math.floor(index / 8);
                return ((this.buffer[bufIndex] >>> (7 - index % 8)) & 1) == 1;
            },
            put: function (num, length) {
                for (var i = 0; i < length; i++) {
                    this.putBit(((num >>> (length - i - 1)) & 1) == 1);
                }
            },
            getLengthInBits: function () {
                return this.length;
            },
            putBit: function (bit) {
                var bufIndex = Math.floor(this.length / 8);
                if (this.buffer.length <= bufIndex) {
                    this.buffer.push(0);
                }
                if (bit) {
                    this.buffer[bufIndex] |= (0x80 >>> (this.length % 8));
                }
                this.length++;
            }
        };

        function QR8bitByte(data) {
            this.mode = QRMode.MODE_8BIT_BYTE;
            this.data = data;
        }
        QR8bitByte.prototype = {
            getLength: function () {
                return this.data.length;
            },
            write: function (buffer) {
                for (var i = 0; i < this.data.length; i++) {
                    buffer.put(this.data.charCodeAt(i), 8);
                }
            }
        };

        function QRCode(typeNumber, errorCorrectionLevel) {
            this.typeNumber = typeNumber;
            this.errorCorrectionLevel = errorCorrectionLevel;
            this.modules = null;
            this.moduleCount = 0;
            this.dataCache = null;
            this.dataList = [];
        }
        QRCode.prototype = {
            addData: function (data) {
                var newData = new QR8bitByte(data);
                this.dataList.push(newData);
                this.dataCache = null;
            },
            isDark: function (row, col) {
                if (row < 0 || this.moduleCount <= row || col < 0 || this.moduleCount <= col) {
                    throw new Error(row + "," + col);
                }
                return this.modules[row][col];
            },
            getModuleCount: function () {
                return this.moduleCount;
            },
            make: function () {
                if (this.typeNumber < 1) {
                    var typeNumber = 1;
                    for (; typeNumber < 40; typeNumber++) {
                        var rsBlocks = QRRSBlock.getRSBlocks(typeNumber, this.errorCorrectionLevel);
                        var buffer = new QRBitBuffer();
                        var totalDataCount = 0;
                        for (var i = 0; i < rsBlocks.length; i++) {
                            totalDataCount += rsBlocks[i].dataCount;
                        }
                        for (var i = 0; i < this.dataList.length; i++) {
                            var data = this.dataList[i];
                            buffer.put(data.mode, 4);
                            buffer.put(data.getLength(), QRUtil.getLengthInBits(data.mode, typeNumber));
                            data.write(buffer);
                        }
                        if (buffer.getLengthInBits() <= totalDataCount * 8) break;
                    }
                    this.typeNumber = typeNumber;
                }
                this.makeImpl(false, this.getBestMaskPattern());
            },
            makeImpl: function (test, maskPattern) {
                this.moduleCount = this.typeNumber * 4 + 17;
                this.modules = new Array(this.moduleCount);
                for (var row = 0; row < this.moduleCount; row++) {
                    this.modules[row] = new Array(this.moduleCount);
                    for (var col = 0; col < this.moduleCount; col++) {
                        this.modules[row][col] = null;
                    }
                }
                this.setupPositionProbePattern(0, 0);
                this.setupPositionProbePattern(this.moduleCount - 7, 0);
                this.setupPositionProbePattern(0, this.moduleCount - 7);
                this.setupPositionAdjustPattern();
                this.setupTimingPattern();
                this.setupTypeInfo(test, maskPattern);
                if (this.typeNumber >= 7) this.setupTypeNumber(test);
                if (this.dataCache == null) {
                    this.dataCache = QRCode.createData(this.typeNumber, this.errorCorrectionLevel, this.dataList);
                }
                this.mapData(this.dataCache, maskPattern);
            },
            setupPositionProbePattern: function (row, col) {
                for (var r = -1; r <= 7; r++) {
                    if (row + r <= -1 || this.moduleCount <= row + r) continue;
                    for (var c = -1; c <= 7; c++) {
                        if (col + c <= -1 || this.moduleCount <= col + c) continue;
                        if ((0 <= r && r <= 6 && (c == 0 || c == 6)) || (0 <= c && c <= 6 && (r == 0 || r == 6)) || (2 <= r && r <= 4 && 2 <= c && c <= 4)) {
                            this.modules[row + r][col + c] = true;
                        } else {
                            this.modules[row + r][col + c] = false;
                        }
                    }
                }
            },
            getBestMaskPattern: function () {
                var minLostPoint = 0;
                var pattern = 0;
                for (var i = 0; i < 8; i++) {
                    this.makeImpl(true, i);
                    var lostPoint = QRUtil.getLostPoint(this);
                    if (i == 0 || minLostPoint > lostPoint) {
                        minLostPoint = lostPoint;
                        pattern = i;
                    }
                }
                return pattern;
            },
            setupTimingPattern: function () {
                for (var r = 8; r < this.moduleCount - 8; r++) {
                    if (this.modules[r][6] != null) continue;
                    this.modules[r][6] = (r % 2 == 0);
                }
                for (var c = 8; c < this.moduleCount - 8; c++) {
                    if (this.modules[6][c] != null) continue;
                    this.modules[6][c] = (c % 2 == 0);
                }
            },
            setupPositionAdjustPattern: function () {
                var pos = QRUtil.getPatternPosition(this.typeNumber);
                for (var i = 0; i < pos.length; i++) {
                    for (var j = 0; j < pos.length; j++) {
                        var row = pos[i];
                        var col = pos[j];
                        if (this.modules[row][col] != null) continue;
                        for (var r = -2; r <= 2; r++) {
                            for (var c = -2; c <= 2; c++) {
                                if (r == -2 || r == 2 || c == -2 || c == 2 || (r == 0 && c == 0)) {
                                    this.modules[row + r][col + c] = true;
                                } else {
                                    this.modules[row + r][col + c] = false;
                                }
                            }
                        }
                    }
                }
            },
            setupTypeNumber: function (test) {
                var bits = QRUtil.getBCHTypeNumber(this.typeNumber);
                for (var i = 18; i < 32; i++) {
                    var mod = (!test && ((bits >> (32 - i - 1)) & 1) == 1);
                    this.modules[Math.floor(i / 3)][i % 3 + this.moduleCount - 8 - 3] = mod;
                }
            },
            setupTypeInfo: function (test, maskPattern) {
                var data = (this.errorCorrectionLevel << 3) | maskPattern;
                var bits = QRUtil.getBCHTypeInfo(data);
                for (var i = 0; i < 15; i++) {
                    var mod = (!test && ((bits >> i) & 1) == 1);
                    if (i < 6) {
                        this.modules[i][8] = mod;
                    } else if (i < 8) {
                        this.modules[i + 1][8] = mod;
                    } else {
                        this.modules[this.moduleCount - 15 + i][8] = mod;
                    }
                }
                for (var i = 0; i < 15; i++) {
                    var mod = (!test && ((bits >> i) & 1) == 1);
                    if (i < 8) {
                        this.modules[8][this.moduleCount - i - 1] = mod;
                    } else if (i < 9) {
                        this.modules[8][15 - i - 1 + 1] = mod;
                    } else {
                        this.modules[8][15 - i - 1] = mod;
                    }
                }
                this.modules[this.moduleCount - 8][8] = (!test);
            },
            mapData: function (data, maskPattern) {
                var inc = -1;
                var row = this.moduleCount - 1;
                var bitIndex = 7;
                var byteIndex = 0;
                for (var col = this.moduleCount - 1; col > 0; col -= 2) {
                    if (col == 6) col--;
                    while (true) {
                        for (var c = 0; c < 2; c++) {
                            if (this.modules[row][col - c] == null) {
                                var dark = false;
                                if (byteIndex < data.length) {
                                    dark = (((data[byteIndex] >>> bitIndex) & 1) == 1);
                                }
                                var mask = QRUtil.getMask(maskPattern, row, col - c);
                                if (mask) dark = !dark;
                                this.modules[row][col - c] = dark;
                                bitIndex--;
                                if (bitIndex == -1) {
                                    byteIndex++;
                                    bitIndex = 7;
                                }
                            }
                        }
                        row += inc;
                        if (row < 0 || this.moduleCount <= row) {
                            row -= inc;
                            inc = -inc;
                            break;
                        }
                    }
                }
            },
            createSvgTag: function (cellSize, margin) {
                cellSize = cellSize || 2;
                margin = (typeof margin == "undefined") ? 4 : margin;
                var size = this.getModuleCount() * cellSize + margin * 2;
                var svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 " + size + " " + size + "\" width=\"" + size + "\" height=\"" + size + "\"><rect width=\"" + size + "\" height=\"" + size + "\" fill=\"#fff\"/><path d=\"";
                for (var r = 0; r < this.getModuleCount(); r++) {
                    for (var c = 0; c < this.getModuleCount(); c++) {
                        if (this.isDark(r, c)) {
                            var x = c * cellSize + margin;
                            var y = r * cellSize + margin;
                            svg += "M" + x + "," + y + "h" + cellSize + "v" + cellSize + "h-" + cellSize + "z";
                        }
                    }
                }
                svg += "\" fill=\"#000\"/></svg>";
                return svg;
            }
        };

        QRCode.createData = function (typeNumber, errorCorrectionLevel, dataList) {
            var rsBlocks = QRRSBlock.getRSBlocks(typeNumber, errorCorrectionLevel);
            var buffer = new QRBitBuffer();
            for (var i = 0; i < dataList.length; i++) {
                var data = dataList[i];
                buffer.put(data.mode, 4);
                buffer.put(data.getLength(), QRUtil.getLengthInBits(data.mode, typeNumber));
                data.write(buffer);
            }
            var totalDataCount = 0;
            for (var i = 0; i < rsBlocks.length; i++) {
                totalDataCount += rsBlocks[i].dataCount;
            }
            if (buffer.getLengthInBits() > totalDataCount * 8) {
                throw new Error("data too long");
            }
            if (buffer.getLengthInBits() + 4 <= totalDataCount * 8) {
                buffer.put(0, 4);
            }
            while (buffer.getLengthInBits() % 8 != 0) {
                buffer.putBit(false);
            }
            while (true) {
                if (buffer.getLengthInBits() >= totalDataCount * 8) break;
                buffer.put(0xEC, 8);
                if (buffer.getLengthInBits() >= totalDataCount * 8) break;
                buffer.put(0x11, 8);
            }
            return QRCode.createBytes(buffer, rsBlocks);
        };

        QRCode.createBytes = function (buffer, rsBlocks) {
            var offset = 0;
            var maxDcCount = 0;
            var maxEcCount = 0;
            var dcdata = new Array(rsBlocks.length);
            var ecdata = new Array(rsBlocks.length);
            for (var r = 0; r < rsBlocks.length; r++) {
                var dcCount = rsBlocks[r].dataCount;
                var ecCount = rsBlocks[r].totalCount - dcCount;
                maxDcCount = Math.max(maxDcCount, dcCount);
                maxEcCount = Math.max(maxEcCount, ecCount);
                dcdata[r] = new Array(dcCount);
                for (var i = 0; i < dcdata[r].length; i++) {
                    dcdata[r][i] = 0xff & buffer.buffer[i + offset];
                }
                offset += dcCount;
                var rsPoly = QRUtil.getErrorCorrectPolynomial(ecCount);
                var rawPoly = new QRPolynomial(dcdata[r], rsPoly.getLength() - 1);
                var modPoly = rawPoly.mod(rsPoly);
                ecdata[r] = new Array(rsPoly.getLength() - 1);
                for (var i = 0; i < ecdata[r].length; i++) {
                    var modIndex = i + modPoly.getLength() - ecdata[r].length;
                    ecdata[r][i] = (modIndex >= 0) ? modPoly.get(modIndex) : 0;
                }
            }
            var totalCodeCount = 0;
            for (var i = 0; i < rsBlocks.length; i++) {
                totalCodeCount += rsBlocks[i].totalCount;
            }
            var data = new Array(totalCodeCount);
            var index = 0;
            for (var i = 0; i < maxDcCount; i++) {
                for (var r = 0; r < rsBlocks.length; r++) {
                    if (i < dcdata[r].length) {
                        data[index++] = dcdata[r][i];
                    }
                }
            }
            for (var i = 0; i < maxEcCount; i++) {
                for (var r = 0; r < rsBlocks.length; r++) {
                    if (i < ecdata[r].length) {
                        data[index++] = ecdata[r][i];
                    }
                }
            }
            return data;
        };

        return {
            QRCode: QRCode,
            QRErrorCorrectLevel: QRErrorCorrectLevel
        };
    })();

    // Render QR on page load or immediate if DOM already ready
    function renderQr() {
        var container = document.getElementById('vietqr-qr-container');
        if (!container) return;

        var qrData = container.getAttribute('data-qr-code');
        if (!qrData) return;

        try {
            var qr = new qrcodeGenerator.QRCode(0, qrcodeGenerator.QRErrorCorrectLevel.L);
            qr.addData(qrData);
            qr.make();
            container.innerHTML = qr.createSvgTag(4, 4);
        } catch (e) {
            container.innerHTML = '<p class="text-red-600">' + (e.message || 'QR render error') + '</p>';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderQr);
    } else {
        renderQr();
    }
})();
