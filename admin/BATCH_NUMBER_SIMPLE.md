# Simple Auto-Generate Batch Number

## Batch Number Format

**Format:** `B` + 6 random digits

### Examples:
- `B834729`
- `B102847`
- `B456123`
- `B000001`
- `B999999`

**Simple and Clean!** ✨

---

## How It Works

### Auto-Generation:
1. Click **"Add Medicine"** → Batch number auto-fills (e.g., `B834729`)
2. Click **"Generate"** button → Get a new random batch number
3. **One million** possible unique combinations (000000-999999)

### Format Details:
- **B** = Batch prefix (1 character)
- **6 digits** = Random number from 000000 to 999999
- **Total length** = Only 7 characters!

---

## Features

✅ **Super Simple** - Just B + 6 digits  
✅ **Easy to Read** - Short and clean format  
✅ **Easy to Write** - If manual entry ever needed  
✅ **1 Million Combinations** - More than enough for most pharmacies  
✅ **Auto-Generated** - No manual work required  
✅ **One-Click Regenerate** - Click Generate for new number  

---

## Comparison

### Old Format:
`BTH-20251015-3847` (17 characters) ❌ Too long

### New Format:
`B834729` (7 characters) ✅ **Much better!**

---

## Usage

**Adding New Medicine:**
```
1. Click "Add Medicine"
2. Batch number auto-fills: B834729
3. Fill other details
4. Save!
```

**Need Different Number?**
```
Click the "Generate" button 🔄
New number appears: B456123
```

---

## Technical Details

### JavaScript Function:
```javascript
function generateBatchNumber() {
    // B + 6 random digits (000000-999999)
    const random = String(Math.floor(Math.random() * 1000000)).padStart(6, '0');
    const batchNumber = `B${random}`;
    document.getElementById('batch_number').value = batchNumber;
    return batchNumber;
}
```

### Possible Numbers:
- **Range:** B000000 to B999999
- **Total:** 1,000,000 unique batch numbers
- **More than enough** for any pharmacy system!

---

## Benefits

1. **Quick to Type** - If manual entry needed
2. **Easy to Remember** - Simple pattern
3. **Easy to Say** - "B eight three four seven two nine"
4. **Space Efficient** - Takes less space in displays/reports
5. **Professional** - Clean and modern look

---

**Perfect Balance:** Simple enough to use, unique enough to track! 🎯

---

*Updated: Now*  
*Format: B + 6 digits*  
*Status: ✅ Active*

