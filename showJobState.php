<!doctype html>
<html lang="it-It">
<head>
    <meta charset="UTF-8">
    <title>Find and Compare</title>

	<style type="text/css">
		h5 {
			margin-bottom: 5px;
		}
	</style>
</head>
<body>

	<h3>Elaborazione della richiesta</h3>
	<h5>Url da comparare:</h5>
	<div><?=$this->firstUrl?></div>
	<div><?=$this->secondUrl?></div>

	<h5>Operazione Eseguita:</h5>
	<div><?=$stepMessage?></div>

	<h5>Tempo trascorso:</h5>
	<div><?=$elapsedTime?> <?=$elapsedTime===1?"secondo":"secondi"?></div>

	<h5>Tempo totale trascorso:</h5>
	<div><?=$totalElapsedTime?> <?=$totalElapsedTime===1?"secondo":"secondi"?></div>

	<br><br>
	<a href="<?=$redirectUrl?>">Prosegui</a>

	<script type="text/javascript">
		setTimeout(function () {
			window.location = "<?=$redirectUrl?>";
        }, 3000);
	</script>
</body>
</html>