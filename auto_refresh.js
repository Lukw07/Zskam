function setupAutoRefresh(containerId, url, interval = 60000) {
    const container = document.getElementById(containerId);
    if (!container) return;

    function refreshContent() {
        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.getElementById(containerId);
                
                if (newContent) {
                    container.innerHTML = newContent.innerHTML;
                }
            })
            .catch(error => console.error('Chyba při aktualizaci obsahu:', error));
    }

    // První aktualizace po načtení stránky
    setTimeout(refreshContent, 1000);

    // Nastavení periodické aktualizace
    setInterval(refreshContent, interval);
} 