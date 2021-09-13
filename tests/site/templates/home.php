<?php
$username = 'api@kirby3-boost.frb.io';
$password = 'kirby3boost';
?><html>
	<head>
		<title>Boost Plugin</title>
		<script src="//unpkg.com/alpinejs" defer></script>
		<script src="//unpkg.com/axios/dist/axios.min.js" defer></script>
        <script src="//unpkg.com/json-format-highlight@1.0.1/dist/json-format-highlight.js" defer></script>
        <link href="//unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
        <style>
	       svg.spinner{width:40px;height:40px;x:0;y:0;viewbox:0 0 40 40}
	       svg.spinner circle{fill:transparent;stroke:currentColor;stroke-width:4;stroke-linecap:round;stroke-dasharray:125.6;transform-origin:20px 20px 0;animation:spinner 2s linear infinite}
	       @keyframes spinner{0%{transform:rotate(0deg);stroke-dashoffset:26.4}50%{transform:rotate(720deg);stroke-dashoffset:125.6}100%{transform:rotate(1080deg);stroke-dashoffset:26.4}}
		</style>
	</head>
	<body>
		<main x-data="{ 
			input: '', 
			output: '',  
			fetchKQL() {
				let send = this.input
				try {
			        send = JSON.parse(send);
			    } catch (e) {
			    	this.output = ''
			        return
			    }
				this.output = undefined
				const auth = {
				  username: '<?= $username ?>',
				  password: '<?= $password ?>'
				};
		        axios.post('<?= site()->url() ?>/api/query', send, { auth })
		        .then(response => {
		            const json = response.data;
		            this.output = jsonFormatHighlight(json)
		        })
		        .catch(error => console.error(error));
			}
		}" class="grid grid-cols-12">
			<nav class="col-span-2 bg-blue-100 text-blue-900 min-h-screen">
				<div class="font-bold p-4">Kirby3 Boost</div>
				<ul class="">
					<li class="border-t border-blue-200"><a href="https://github.com/bnomei/kirby3-boost" class="block hover:bg-blue-200 p-4 w-full text-left">Github</a></li>
					<li class="border-t border-blue-200"><a href="<?= site()->url() ?>/benchmark" class="block hover:bg-blue-200 p-4 w-full text-left">Benchmark</a></li>
				</ul>
				<div class="font-bold p-4 mt-12">Predefined queries</div>
				<ul class="">
					<?php foreach(site()->children()->filterBy('template', 'kqlquery') as $q): ?>
					<li class="border-t border-blue-200">
						<button @click="input = $el.firstElementChild.text; fetchKQL()"
							class="hover:bg-blue-200 p-4 w-full text-left"
						><script type="text/json"><?= $q->json()->toInlineJson() ?></script><span><?= $q->title()->html() ?></span></button></li>
					<?php endforeach; ?>
				</ul>
			</nav>
			<div class="col-span-5 border-r border-blue-200"><textarea class="focus:outline-none w-full h-full p-4 bg-blue-50 text-blue-800 font-mono" x-model="input" placeholder="Enter query" @input.debounce="fetchKQL()" autofocus autocomplete="false"></textarea></div>
			<div class="col-span-5">
				<pre x-show="output != undefined && output.length > 0" class="p-4 font-mono" x-html="output"></pre>
				<div x-show="output == undefined" class="flex items-center justify-center min-h-screen"><div><svg class="spinner text-blue-200"><circle cx="20" cy="20" r="18"></circle></svg></div></div>
			</div>
		</main>
		<footer class="absolute bottom-0 w-full bg-yellow-200 text-yellow-800 p-4 grid gap-4 grid-cols-12 text-sm">
			<div class="col-span-4 font-mono"><?= site()->url() ?>/api/query<br><?= $username ?>:<?= $password ?></div>
			<div class="col-span-8">Performance of Kirby 3 CMS with a lot of page objects is improved by the <a class="underline" href="https://github.com/bnomei/kirby3-boost">Boost Plugin</a> using the <a class="underline" href="https://github.com/bnomei/kirby3-sqlite-cachedriver">SQLite Cache Driver</a>. Queries are sent to the public API endpoint of the <a class="underline" href="https://github.com/getkirby/kql">KQL Plugin</a>. You can either use this interactive playground or a tool like HTTPie, Insomnia or Postman to connect to the API. Have fun exploring... 
			</div>
		</footer>
	</body>
</html>