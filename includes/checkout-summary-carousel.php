          <div class="summary-carousel-card" id="summaryCarouselCard">

            <!-- BARRE DE CONTRÔLE ET PROGRESSION EN HAUT DU TABLEAU -->
            <div class="carousel-nav-header">
              <div class="carousel-status-badge">
                <span class="pulse-green-dot"></span>
                <span id="carouselSlideLabel">Offre d'adhésion</span>
              </div>
              <div class="carousel-indicators" id="carouselIndicators">
                <button type="button" class="indicator-dot active" data-slide="0" aria-label="Offre 9€"></button>
                <button type="button" class="indicator-dot" data-slide="1" aria-label="Avis 1"></button>
                <button type="button" class="indicator-dot" data-slide="2" aria-label="Avis 2"></button>
                <button type="button" class="indicator-dot" data-slide="3" aria-label="Avis 3"></button>
                <button type="button" class="indicator-dot" data-slide="4" aria-label="Avis 4"></button>
                <button type="button" class="indicator-dot" data-slide="5" aria-label="Avis 5"></button>
                <button type="button" class="indicator-dot" data-slide="6" aria-label="Avis 6"></button>
              </div>
              <div class="carousel-pause-hint" title="L'animation se met en pause au survol de la souris">
                <span id="carouselTimerText">5s</span>
              </div>
            </div>

            <!-- FENÊTRE D'ANIMATION VERTICALE (SLIDER QUI MONTE VERS LE HAUT) -->
            <div class="summary-carousel-viewport" id="summaryCarouselViewport">
              <div class="summary-carousel-track" id="summaryCarouselTrack">

                <?php
                  $isCreatorPlan = (($selectedPlan ?? 'member') === 'creator');
                ?>
                <!-- SLIDE 0 : TABLEAU ACCÈS (OFFRE SÉLECTIONNÉE) -->
                <div class="summary-slide" data-slide-index="0">
                  <div>
                    <div class="summary-header">
                      <span class="summary-pill" id="summaryPillTitle"><?= $isCreatorPlan ? 'Accès Créateur & Host Mastermind' : 'Accès Membre Illimité' ?></span>
                      <h2 class="summary-title">One Vision Community</h2>
                      <p class="summary-desc" id="summaryDescText">
                        <?= $isCreatorPlan 
                            ? 'Créez vos lives, diffusez vos masterminds et animez la communauté.' 
                            : 'L\'espace d\'entraide, de lives interactifs et de partenariats des entrepreneurs ambitieux.' ?>
                      </p>
                    </div>

                    <ul class="summary-features-list" id="summaryFeaturesList">
                      <li id="summaryFeatureHost" style="<?= $isCreatorPlan ? 'display:flex;' : 'display:none;' ?> color:#e63946;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#e63946" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                        <span><strong>Création & animation illimitée de Lives / Masterminds</strong></span>
                      </li>
                      <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span><strong>4+ Lives & Masterminds</strong> interactifs en visio chaque mois</span>
                      </li>
                      <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span><strong>Salons d'échanges privés</strong> par thématiques 24h/24 & 7j/7</span>
                      </li>
                      <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span><strong>Replays intégraux HD</strong> et bibliothèque de fiches outils</span>
                      </li>
                      <li>
                        <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span><strong>Réseau qualifié</strong> : +1 200 pairs actifs prêts à collaborer</span>
                      </li>
                    </ul>

                    <div class="summary-pricing-box">
                      <div class="pricing-line">
                        <span>Adhésion mensuelle</span>
                        <span class="price-val" id="summaryPriceVal"><?= $isCreatorPlan ? '29,00 €' : '9,00 €' ?> <small id="summaryPriceXofVal">(<?= $isCreatorPlan ? '~19 000 FCFA' : '~5 900 FCFA' ?>)</small></span>
                      </div>
                      <div class="pricing-line">
                        <span>Frais d'activation</span>
                        <span class="price-free">OFFERTS (0 €)</span>
                      </div>
                      <div class="pricing-line total-line">
                        <span>Total à régler aujourd'hui</span>
                        <span class="total-amount" id="summaryTotalAmount"><?= $isCreatorPlan ? '29,00 €' : '9,00 €' ?> <span class="recur-text">/ mois</span></span>
                      </div>
                    </div>
                  </div>

                  <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:0.65rem 0.85rem; font-size:0.78rem; color:#15803d; display:flex; align-items:center; justify-content:space-between; margin-top:0.75rem;">
                    <span>💬 <strong>Retours & avis des membres</strong></span>
                    <span style="font-weight:700; color:#16a34a;">Défilement automatique ↓</span>
                  </div>
                </div>

                <!-- SLIDE 1 : TÉMOIGNAGE 1 (JULIEN B.) -->
                <div class="summary-slide" data-slide-index="1">
                  <div>
                    <div class="capture-app-header">
                      <span class="capture-channel-tag">💬 #entraide-acquisition</span>
                      <span class="capture-verified-tag">✓ Membre vérifié</span>
                    </div>

                    <div class="community-capture-card">
                      <div class="capture-author-row">
                        <div class="capture-avatar-wrap">
                          <img src="./img/avatar-julien.jpg" alt="Julien B." class="capture-avatar-img" onerror="this.src='./img/avatar-aurore.jpg'">
                          <span class="capture-online-badge"></span>
                        </div>
                        <div class="capture-author-info">
                          <div class="capture-author-name">
                            <span>Julien B.</span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="#3b82f6"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                          </div>
                          <div class="capture-author-role">Fondateur SaaS B2B • Rejoint il y a 3 semaines</div>
                        </div>
                      </div>

                      <div class="capture-stars-row">
                        ★★★★★ <span class="capture-stars-score">5.0 / 5</span>
                      </div>

                      <p class="capture-message-text">
                        « Franchement pour <strong>9€/mois</strong> c'est indécent. En 2 semaines dans le salon B2B j'ai trouvé mon <strong>premier client beta</strong> et les retours sur mon tunnel d'acquisition étaient 10x plus concrets que n'importe quelle formation à 1000€. L'entraide est ultra réelle ici ! »
                      </p>

                      <div class="capture-result-box">
                        🎯 <span><strong>Résultat :</strong> 1er client B2B signé en 14 jours</span>
                      </div>

                      <div class="capture-reactions-row">
                        <span class="reaction-chip">🔥 34</span>
                        <span class="reaction-chip">🚀 19</span>
                        <span class="reaction-chip">❤️ 28</span>
                        <span class="reaction-chip">👏 22</span>
                      </div>

                      <div class="capture-reply-thread">
                        <strong>Maxime (Fondateur One Vision)</strong> : « Bravo Julien ! C'est exactement pour cette entraide concrète qu'on a créé les salons. »
                      </div>
                    </div>
                  </div>

                  <div class="slide-bottom-bar">
                    <button type="button" class="slide-recap-btn" onclick="window.summaryCarouselJump(0)">↺ Revoir l'offre 9€</button>
                    <span class="slide-guarantee-text">🔒 Sans engagement • Annulable en 1 clic</span>
                  </div>
                </div>

                <!-- SLIDE 2 : TÉMOIGNAGE 2 (SOPHIE M.) -->
                <div class="summary-slide" data-slide-index="2">
                  <div>
                    <div class="capture-app-header">
                      <span class="capture-channel-tag">💬 #mastermind-hebdo</span>
                      <span class="capture-verified-tag">✓ Membre vérifiée</span>
                    </div>

                    <div class="community-capture-card">
                      <div class="capture-author-row">
                        <div class="capture-avatar-wrap">
                          <img src="./img/avatar-sophie.jpg" alt="Sophie M." class="capture-avatar-img" onerror="this.src='./img/avatar-aurore.jpg'">
                          <span class="capture-online-badge"></span>
                        </div>
                        <div class="capture-author-info">
                          <div class="capture-author-name">
                            <span>Sophie M.</span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="#3b82f6"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                          </div>
                          <div class="capture-author-role">Copywriter & Stratège Freelance • Rejointe en Septembre</div>
                        </div>
                      </div>

                      <div class="capture-stars-row">
                        ★★★★★ <span class="capture-stars-score">5.0 / 5</span>
                      </div>

                      <p class="capture-message-text">
                        « L'ambiance est incroyable ! Pas de faux gourous, que des entrepreneurs bienveillants qui <strong>partagent leurs vrais chiffres et leurs blocages</strong> sans filtre. Les masterminds du jeudi soir sont devenus mon rituel indispensable pour garder le cap et progresser. »
                      </p>

                      <div class="capture-result-box">
                        📈 <span><strong>Résultat :</strong> CA freelance doublé grâce aux recommandations</span>
                      </div>

                      <div class="capture-reactions-row">
                        <span class="reaction-chip">❤️ 39</span>
                        <span class="reaction-chip">👏 25</span>
                        <span class="reaction-chip">💡 18</span>
                        <span class="reaction-chip">🚀 14</span>
                      </div>

                      <div class="capture-reply-thread">
                        <strong>Clara D. (Membre)</strong> : « Tellement d'accord Sophie, ta présentation au dernier mastermind était magistrale ! »
                      </div>
                    </div>
                  </div>

                  <div class="slide-bottom-bar">
                    <button type="button" class="slide-recap-btn" onclick="window.summaryCarouselJump(0)">↺ Revoir l'offre 9€</button>
                    <span class="slide-guarantee-text">🔒 4 Masterminds visio par mois inclus</span>
                  </div>
                </div>

                <!-- SLIDE 3 : TÉMOIGNAGE 3 (THOMAS R.) -->
                <div class="summary-slide" data-slide-index="3">
                  <div>
                    <div class="capture-app-header">
                      <span class="capture-channel-tag">💬 #lives-et-coaching</span>
                      <span class="capture-verified-tag">✓ Membre vérifié</span>
                    </div>

                    <div class="community-capture-card">
                      <div class="capture-author-row">
                        <div class="capture-avatar-wrap">
                          <img src="./img/avatar-thomas.jpg" alt="Thomas R." class="capture-avatar-img" onerror="this.src='./img/avatar-aurore.jpg'">
                          <span class="capture-online-badge"></span>
                        </div>
                        <div class="capture-author-info">
                          <div class="capture-author-name">
                            <span>Thomas R.</span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="#3b82f6"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                          </div>
                          <div class="capture-author-role">Coach en leadership • Membre actif</div>
                        </div>
                      </div>

                      <div class="capture-stars-row">
                        ★★★★★ <span class="capture-stars-score">5.0 / 5</span>
                      </div>

                      <p class="capture-message-text">
                        « Le live Q&A de mardi m'a débloqué un problème d'automatisation sur lequel je butais depuis un mois. Un membre m'a envoyé son <strong>template Notion en DM en 5 minutes</strong>. Rien que pour cette solidarité, les 9€ sont rentabilisés 100 fois. »
                      </p>

                      <div class="capture-result-box">
                        ⚡ <span><strong>Résultat :</strong> 8h économisées par semaine sur mes process</span>
                      </div>

                      <div class="capture-reactions-row">
                        <span class="reaction-chip">🚀 27</span>
                        <span class="reaction-chip">👏 34</span>
                        <span class="reaction-chip">❤️ 19</span>
                        <span class="reaction-chip">💪 15</span>
                      </div>

                      <div class="capture-reply-thread">
                        <strong>Alexandre L.</strong> : « C'était avec grand plaisir Thomas, content que le template te fasse gagner du temps ! »
                      </div>
                    </div>
                  </div>

                  <div class="slide-bottom-bar">
                    <button type="button" class="slide-recap-btn" onclick="window.summaryCarouselJump(0)">↺ Revoir l'offre 9€</button>
                    <span class="slide-guarantee-text">🔒 Salons d'entraide ouverts 24h/24 & 7j/7</span>
                  </div>
                </div>

                <!-- SLIDE 4 : TÉMOIGNAGE 4 (CLARA D.) -->
                <div class="summary-slide" data-slide-index="4">
                  <div>
                    <div class="capture-app-header">
                      <span class="capture-channel-tag">💬 #partenariats-reseau</span>
                      <span class="capture-verified-tag">✓ Membre vérifiée</span>
                    </div>

                    <div class="community-capture-card">
                      <div class="capture-author-row">
                        <div class="capture-avatar-wrap">
                          <img src="./img/avatar-clara.jpg" alt="Clara D." class="capture-avatar-img" onerror="this.src='./img/avatar-aurore.jpg'">
                          <span class="capture-online-badge"></span>
                        </div>
                        <div class="capture-author-info">
                          <div class="capture-author-name">
                            <span>Clara D.</span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="#3b82f6"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                          </div>
                          <div class="capture-author-role">Community Manager & Freelance • Rejointe il y a 1 mois</div>
                        </div>
                      </div>

                      <div class="capture-stars-row">
                        ★★★★★ <span class="capture-stars-score">5.0 / 5</span>
                      </div>

                      <p class="capture-message-text">
                        « J'hésitais un peu vu le prix si accessible en me disant que la communauté serait inactive... Au final c'est <strong>la communauté la plus engagée que j'ai vue</strong> ! J'ai déjà conclu <strong>2 missions en sous-traitance</strong> avec d'autres membres le mois dernier. »
                      </p>

                      <div class="capture-result-box">
                        🤝 <span><strong>Résultat :</strong> 2 missions rémunérées signées entre membres</span>
                      </div>

                      <div class="capture-reactions-row">
                        <span class="reaction-chip">🔥 31</span>
                        <span class="reaction-chip">❤️ 26</span>
                        <span class="reaction-chip">🚀 17</span>
                        <span class="reaction-chip">👏 29</span>
                      </div>

                      <div class="capture-reply-thread">
                        <strong>Marc V.</strong> : « C'est ça la puissance du réseau qualifié ! »
                      </div>
                    </div>
                  </div>

                  <div class="slide-bottom-bar">
                    <button type="button" class="slide-recap-btn" onclick="window.summaryCarouselJump(0)">↺ Revoir l'offre 9€</button>
                    <span class="slide-guarantee-text">🔒 Réseau de +1 200 pairs actifs</span>
                  </div>
                </div>

                <!-- SLIDE 5 : TÉMOIGNAGE 5 (MARC V.) -->
                <div class="summary-slide" data-slide-index="5">
                  <div>
                    <div class="capture-app-header">
                      <span class="capture-channel-tag">💬 #coworking-focus</span>
                      <span class="capture-verified-tag">✓ Membre vérifié</span>
                    </div>

                    <div class="community-capture-card">
                      <div class="capture-author-row">
                        <div class="capture-avatar-wrap">
                          <img src="./img/avatar-marc.jpg" alt="Marc V." class="capture-avatar-img" onerror="this.src='./img/avatar-aurore.jpg'">
                          <span class="capture-online-badge"></span>
                        </div>
                        <div class="capture-author-info">
                          <div class="capture-author-name">
                            <span>Marc V.</span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="#3b82f6"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                          </div>
                          <div class="capture-author-role">Mentor Vente Haute Valeur • Membre actif</div>
                        </div>
                      </div>

                      <div class="capture-stars-row">
                        ★★★★★ <span class="capture-stars-score">5.0 / 5</span>
                      </div>

                      <p class="capture-message-text">
                        « La bienveillance et le niveau des échanges sont impressionnants. Pas de spam, les modérateurs font un travail exceptionnel. Les <strong>sessions de coworking virtuel du matin</strong> me font abattre un travail monstre dans la bonne humeur et avec discipline. »
                      </p>

                      <div class="capture-result-box">
                        🎯 <span><strong>Résultat :</strong> Discipline quotidienne & focus x3 dès 8h30</span>
                      </div>

                      <div class="capture-reactions-row">
                        <span class="reaction-chip">👏 42</span>
                        <span class="reaction-chip">🔥 28</span>
                        <span class="reaction-chip">💪 19</span>
                        <span class="reaction-chip">❤️ 21</span>
                      </div>

                      <div class="capture-reply-thread">
                        <strong>Élodie P.</strong> : « Le coworking du matin a complètement changé ma productivité aussi ! »
                      </div>
                    </div>
                  </div>

                  <div class="slide-bottom-bar">
                    <button type="button" class="slide-recap-btn" onclick="window.summaryCarouselJump(0)">↺ Revoir l'offre 9€</button>
                    <span class="slide-guarantee-text">🔒 Émulation collective & sessions quotidiennes</span>
                  </div>
                </div>

                <!-- SLIDE 6 : TÉMOIGNAGE 6 (ÉLODIE P.) -->
                <div class="summary-slide" data-slide-index="6">
                  <div>
                    <div class="capture-app-header">
                      <span class="capture-channel-tag">💬 #replays-et-outils</span>
                      <span class="capture-verified-tag">✓ Membre vérifiée</span>
                    </div>

                    <div class="community-capture-card">
                      <div class="capture-author-row">
                        <div class="capture-avatar-wrap">
                          <img src="./img/avatar-elodie.jpg" alt="Élodie P." class="capture-avatar-img" onerror="this.src='./img/avatar-aurore.jpg'">
                          <span class="capture-online-badge"></span>
                        </div>
                        <div class="capture-author-info">
                          <div class="capture-author-name">
                            <span>Élodie P.</span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="#3b82f6"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                          </div>
                          <div class="capture-author-role">Créatrice E-commerce & Formatrice • Rejointe il y a 2 mois</div>
                        </div>
                      </div>

                      <div class="capture-stars-row">
                        ★★★★★ <span class="capture-stars-score">5.0 / 5</span>
                      </div>

                      <p class="capture-message-text">
                        « La <strong>bibliothèque de replays HD</strong> et les <strong>fiches action téléchargeables</strong> sont une vraie mine d'or. J'ai réutilisé les modèles de contrats et la checklist d'acquisition. Le meilleur investissement pour mon business cette année ! »
                      </p>

                      <div class="capture-result-box">
                        📚 <span><strong>Résultat :</strong> +30 replays & fiches outils immédiatement opérationnels</span>
                      </div>

                      <div class="capture-reactions-row">
                        <span class="reaction-chip">❤️ 38</span>
                        <span class="reaction-chip">🚀 25</span>
                        <span class="reaction-chip">💡 22</span>
                        <span class="reaction-chip">🔥 19</span>
                      </div>

                      <div class="capture-reply-thread">
                        <strong>Maxime (Fondateur One Vision)</strong> : « Merci pour ce beau retour Élodie ! 4 nouveaux replays arrivent cette semaine. »
                      </div>
                    </div>
                  </div>

                  <div class="slide-bottom-bar">
                    <button type="button" class="slide-recap-btn" onclick="window.summaryCarouselJump(0)">↺ Revoir l'offre 9€</button>
                    <span class="slide-guarantee-text">🔒 Bibliothèque de replays HD en libre accès</span>
                  </div>
                </div>

              </div>
            </div>

          </div>
