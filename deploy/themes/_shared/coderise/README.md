# Logo Coderise : ressources partagées des thèmes

Source : `logo_coderise.svg`, fichier fourni par Coderise (juillet 2025, export Inkscape), recopié **tel quel**.
Il fait foi (charte graphique, §2.6 : on ne redessine pas le logo). Les autres fichiers en sont dérivés.

| Fichier | Usage |
|---|---|
| `logo_coderise.svg` | Original. Le texte y est en police Bebas Neue **non embarquée** : à ne pas servir tel quel sur le web. |
| `logo-sur-bleu-nuit.svg` | Logo empilé (symbole + CODERISE) sur carré bleu nuit `#000F26`. Police embarquée. |
| `logo-sur-fond-clair.svg` | Même logo sans fond, parties blanches en bleu nuit (équivalent du « logo couleur sur fond clair » de la charte). |
| `symbole-sur-bleu-nuit.svg` | Symbole seul sur carré bleu nuit : favicon, avatar. |
| `symbole-sur-fond-clair.svg` | Symbole seul, sans fond. |
| `OFL-BebasNeue.txt` | Licence SIL OFL de la police Bebas Neue (`@fontsource/bebas-neue` 5.3.0, sous-ensemble latin). |

## Ce qui a été fait, et ce qui ne l'a pas été
- Coordonnées, tracés et couleurs identiques à l'original. Seuls changent : suppression des métadonnées Inkscape,
  police embarquée en `data:` (famille `Bebas Neue Embedded`), variantes fond clair et symbole seul.
- Les deux rectangles bleu nuit de l'original (`rect2`, `rect3`) **découpent à plat** les pieds du chevron et le haut
  de la barre oblique. Ils sont conservés sur fond bleu nuit, et traduits en masque SVG sur fond clair.
- Rendu vérifié dans Chrome (balise `<img>`) et comparé à l'original.

## À valider / manquant
- Le cyan de ce SVG est `#11c9df`, la charte indique `#10C9DE` (écart de 1 niveau sur R et G) : la couleur du fichier fourni est conservée.
- Pas de logo horizontal (symbole à gauche, CODERISE à droite) en SVG : l'en-tête des thèmes réutilise le PNG détouré
  des maquettes (`docs/theme-mockups-coderise/_shared/`, dérivé de `2.jpg`) en attendant un fichier officiel.
- Pas de versions monochromes officielles : la variante fond clair n'est pas monochrome (le chevron reste cyan).
