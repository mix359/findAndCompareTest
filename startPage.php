<!doctype html>
<html lang="it-It">
<head>
	<meta charset="UTF-8">
	<title>Find and Compare</title>
</head>
<body>

<form action="index.php?act=findAndCompare" method="POST">
	<div>
		<label for="firstUrl">Primo Url:</label>
		<input type="url" name="firstUrl" id="firstUrl" onkeyup="onFieldKeyUp()" />
	</div>

	<div>
		<label for="secondUrl">Secondo Url:</label>
		<input type="url" name="secondUrl" id="secondUrl" onkeyup="onFieldKeyUp()" />
	</div>

	<input type="submit" value="Invia"/>

    <br />
    <br />

    <div id="charCounterContainer">
        Numero di caratteri nei due campi: <span id="charCounter"></span>
    </div>

    <div id="sumAlphabetPositionContainer">
        Somma posizioni nell'alfabeto: <span id="sumAlphabetPosition"></span>
    </div>

</form>

<script type="text/javascript">
    var firstUrlField = document.getElementById("firstUrl");
    var secondUrlField = document.getElementById("secondUrl");
    var charCounter = document.getElementById("charCounter");
    var sumAlphabetPosition = document.getElementById("sumAlphabetPosition");

    function onFieldKeyUp() {
        charCounter.innerText = firstUrlField.value.length + secondUrlField.value.length;

        var sumAlphabetPos = i = 0;
        var charCode;
        for(i = 0; i < firstUrlField.value.length; i++) {
            charCode = firstUrlField.value.charCodeAt(i);
            if(charCode > 64 && charCode < 91) {
                sumAlphabetPos += charCode - 64;
            } else if(charCode > 96 && charCode < 123) {
                sumAlphabetPos += charCode - 96;
            }
        }

        for(i = 0; i < secondUrlField.value.length; i++) {
            charCode = secondUrlField.value.charCodeAt(i);
            if(charCode > 64 && charCode < 91) {
                sumAlphabetPos += charCode - 64;
            } else if(charCode > 96 && charCode < 123) {
                sumAlphabetPos += charCode - 96;
            }
        }

        sumAlphabetPosition.innerText = sumAlphabetPos;
    }
    
</script>

</body>
</html>