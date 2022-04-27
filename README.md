# Badge-Goal

__Widget d'affichage de donation goal__

Variables de l'url :

  - montant : montant actuel (si defini remplace tipeee/utip)
  - goal : montant objectif
  - tipeee_id : recupere le montant sur la page tipeee correspondante (additionne avec les autres si definis)
  **DESACTIVE - Utip n'autorise plus l'acces a cette donnee** - utip_id : recupere le montant sur la page uTip correspondante (additionne avec les autres si definis)
  - twitch_id : recupere le montant des subs twitchs (via le site Twich Tracker) du streamer correspondant (additionne avec les autres si definis)
  - tipeeestream_id : recupere le montant des donations sur la page tipeeestream correspondante (additionne avec les autres si definis)
  - tipeeestream_token : token authentification necessaire pour tipeeestream
  - ajout : ajoute un montant supplementaire manuellement
  - pourcentage : si definit a 1, remplace le montant par un pourcentage
  - couleur : couleur du badge en hexa (sans le '#' devant)
  - label : remplace le montant en € par un texte
  - type : 0 ou 1 pour changer l'apparence (par défaut 0)
  - notext : si definit a 1, masque les explications

Exemple : https://badgegoal.chaosklub.com/bg.php?&montant=69&goal=100&couleur=FF4D3C

Auto-refresh de la page toutes les 2 minutes
