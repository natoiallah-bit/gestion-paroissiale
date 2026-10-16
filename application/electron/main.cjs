// Application Windows : ouvre l'interface (dossier dist) dans une fenêtre.
const { app, BrowserWindow, Menu, shell } = require('electron');
const path = require('path');

function creerFenetre() {
  const fenetre = new BrowserWindow({
    width: 1280,
    height: 820,
    minWidth: 360,
    title: 'Gestion Paroissiale',
    icon: path.join(__dirname, '..', 'public', 'icone.png'),
    autoHideMenuBar: true,
    webPreferences: { contextIsolation: true, nodeIntegration: false, sandbox: true },
  });
  Menu.setApplicationMenu(null);
  fenetre.loadFile(path.join(__dirname, '..', 'dist', 'index.html'));

  // Les liens externes s'ouvrent dans le navigateur, pas dans l'application
  fenetre.webContents.setWindowOpenHandler(({ url }) => {
    if (/^https?:/.test(url)) shell.openExternal(url);
    return { action: 'deny' };
  });
}

if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on('second-instance', () => {
    const [fenetre] = BrowserWindow.getAllWindows();
    if (fenetre) { if (fenetre.isMinimized()) fenetre.restore(); fenetre.focus(); }
  });
  app.whenReady().then(creerFenetre);
  app.on('window-all-closed', () => app.quit());
}
