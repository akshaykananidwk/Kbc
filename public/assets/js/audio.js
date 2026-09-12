/**
 * Ganpati Bapa Quiz Show — audio engine.
 *
 * Two layers:
 *   1. Music beds (intro, background, suspense, victory) played from the
 *      files an admin uploads in Settings → Music.
 *   2. Short effects. If no file is uploaded for an event, an original
 *      tone is synthesised with the Web Audio API — so the show has sound
 *      out of the box with no copyrighted material anywhere.
 *
 * Browsers block audio until the page has been interacted with once, so
 * everything is armed on the first click, key press or touch.
 */
(function (global) {
  'use strict';

  function QuizAudio(config) {
    this.config = config || {};
    this.files = this.config.sounds || {};
    this.music = this.config.music || {};
    this.enabled = this.config.soundEnabled !== false;
    this.musicEnabled = this.config.musicEnabled !== false;
    this.useSynth = this.config.synthFallback !== false;
    this.effectVolume = Math.max(0, Math.min(1, (this.config.soundVolume || 80) / 100));
    this.musicVolume = Math.max(0, Math.min(1, (this.config.musicVolume || 25) / 100));
    this.duck = this.config.duckOnEffect !== false;

    this.unlocked = false;
    this.context = null;
    this.players = {};
    this.currentBed = null;
    this.currentBedName = '';

    this._armUnlock();
  }

  /* --- Unlocking -------------------------------------------------------- */
  /**
   * Opens the audio context. Safe to call directly from a click handler
   * (Settings → Test sound does exactly that) as well as from the
   * first-interaction listeners below.
   */
  QuizAudio.prototype.unlockNow = function () {
    if (this.unlocked) {
      if (this.context && this.context.state === 'suspended') { this.context.resume(); }
      return;
    }
    this.unlocked = true;
    try {
      var Ctx = global.AudioContext || global.webkitAudioContext;
      if (Ctx) {
        this.context = new Ctx();
        if (this.context.state === 'suspended') { this.context.resume(); }
      }
    } catch (e) { this.context = null; }
    if (this._pendingBed) {
      this.playBed(this._pendingBed);
      this._pendingBed = null;
    }
    if (typeof this.onUnlock === 'function') { this.onUnlock(); }
  };

  QuizAudio.prototype._armUnlock = function () {
    var self = this;
    var unlock = function () { self.unlockNow(); };
    ['click', 'keydown', 'touchstart', 'pointerdown'].forEach(function (evt) {
      global.document.addEventListener(evt, unlock, { once: true, passive: true });
    });
  };

  QuizAudio.prototype.isUnlocked = function () { return this.unlocked; };

  /* --- Music beds -------------------------------------------------------- */
  QuizAudio.prototype.playBed = function (name) {
    if (!this.musicEnabled) return;
    var src = this.music[name];
    if (!src) { this.stopBed(); return; }
    if (this.currentBedName === name && this.currentBed && !this.currentBed.paused) { return; }

    if (!this.unlocked) { this._pendingBed = name; return; }

    this.stopBed();

    try {
      var audio = new Audio(src);
      audio.loop = name === 'background' || (name === 'intro' && this.config.introLoop !== false);
      audio.volume = 0;
      var target = name === 'background' ? this.musicVolume : Math.min(1, this.musicVolume * 2.2);
      audio.play().then(function () {
        // Fade in so the music never slams in mid-sentence.
        var steps = 20, i = 0;
        var timer = global.setInterval(function () {
          i++;
          audio.volume = Math.min(target, (target / steps) * i);
          if (i >= steps) global.clearInterval(timer);
        }, 40);
      }).catch(function () { /* autoplay refused - harmless */ });

      this.currentBed = audio;
      this.currentBedName = name;
      this._bedTarget = target;
    } catch (e) { /* ignore */ }
  };

  QuizAudio.prototype.stopBed = function () {
    var bed = this.currentBed;
    if (!bed) return;
    this.currentBed = null;
    this.currentBedName = '';
    try {
      var steps = 12, i = 0, start = bed.volume;
      var timer = global.setInterval(function () {
        i++;
        bed.volume = Math.max(0, start - (start / steps) * i);
        if (i >= steps) { global.clearInterval(timer); bed.pause(); }
      }, 30);
    } catch (e) { try { bed.pause(); } catch (e2) {} }
  };

  /** Briefly lower the music bed so an effect can be heard over it. */
  QuizAudio.prototype._duck = function (ms) {
    if (!this.duck || !this.currentBed) return;
    var bed = this.currentBed;
    var target = this._bedTarget || this.musicVolume;
    try {
      bed.volume = Math.max(0, target * 0.25);
      global.setTimeout(function () {
        try { bed.volume = target; } catch (e) {}
      }, ms || 1400);
    } catch (e) { /* ignore */ }
  };

  /* --- Effects ----------------------------------------------------------- */
  QuizAudio.prototype.play = function (event) {
    if (!this.enabled || !this.unlocked) return;

    var src = this.files[event];
    if (src) {
      this._duck(1600);
      try {
        if (!this.players[event]) { this.players[event] = new Audio(src); }
        var player = this.players[event];
        player.volume = this.effectVolume;
        player.currentTime = 0;
        var promise = player.play();
        if (promise && promise.catch) { promise.catch(function () {}); }
      } catch (e) { /* ignore */ }
      return;
    }

    if (this.useSynth) {
      this._duck(1100);
      this._synth(event);
    }
  };

  /**
   * Original, royalty-free tones built from oscillators.
   * Nothing here is sampled from any broadcast.
   */
  QuizAudio.prototype._synth = function (event) {
    var ctx = this.context;
    if (!ctx) return;

    var recipes = {
      // ascending fourth — a question arrives
      question_start: { notes: [[392, 0, 0.16], [523.25, 0.13, 0.3]], type: 'triangle', gain: 0.16 },
      // soft tick pair — the clock begins
      timer_start:    { notes: [[880, 0, 0.07], [880, 0.16, 0.07]], type: 'square', gain: 0.08 },
      // firm double thud — the answer is committed
      answer_lock:    { notes: [[196, 0, 0.14], [147, 0.12, 0.22]], type: 'sawtooth', gain: 0.14 },
      // bright major arpeggio — correct
      correct_answer: { notes: [[523.25, 0, 0.16], [659.25, 0.12, 0.16], [783.99, 0.24, 0.34]], type: 'triangle', gain: 0.2 },
      // falling minor second — wrong
      wrong_answer:   { notes: [[311.13, 0, 0.24], [233.08, 0.2, 0.5]], type: 'sawtooth', gain: 0.16 },
      // shimmer — a prize is banked
      prize_won:      { notes: [[659.25, 0, 0.12], [880, 0.1, 0.12], [1174.66, 0.2, 0.3]], type: 'sine', gain: 0.15 },
      // swirl — lifeline
      lifeline_used:  { notes: [[440, 0, 0.1], [587.33, 0.08, 0.1], [440, 0.16, 0.18]], type: 'triangle', gain: 0.13 },
      // full fanfare — the big win
      final_win:      { notes: [[523.25, 0, 0.18], [659.25, 0.15, 0.18], [783.99, 0.3, 0.18], [1046.5, 0.45, 0.6]], type: 'triangle', gain: 0.22 },
      // descending close — game over
      game_over:      { notes: [[392, 0, 0.2], [329.63, 0.18, 0.2], [261.63, 0.36, 0.55]], type: 'sine', gain: 0.16 },
      // single tick for the last seconds
      tick:           { notes: [[1046.5, 0, 0.04]], type: 'square', gain: 0.06 }
    };

    var recipe = recipes[event];
    if (!recipe) return;

    var self = this;
    recipe.notes.forEach(function (note) {
      var frequency = note[0], delay = note[1], duration = note[2];
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.type = recipe.type;
      osc.frequency.value = frequency;

      var start = ctx.currentTime + delay;
      var peak = recipe.gain * self.effectVolume;
      gain.gain.setValueAtTime(0.0001, start);
      gain.gain.exponentialRampToValueAtTime(Math.max(0.0002, peak), start + 0.012);
      gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);

      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(start);
      osc.stop(start + duration + 0.03);
    });
  };

  /* --- Runtime updates ---------------------------------------------------- */
  QuizAudio.prototype.update = function (settings) {
    if (!settings) return;
    if (settings.sounds) { this.files = settings.sounds; }
    if (settings.music) { this.music = settings.music; }
    if (typeof settings.sound === 'boolean') { this.enabled = settings.sound; }
    if (typeof settings.music_enabled === 'boolean') {
      this.musicEnabled = settings.music_enabled;
      if (!this.musicEnabled) { this.stopBed(); }
    }
    if (typeof settings.music_volume === 'number') {
      this.musicVolume = Math.max(0, Math.min(1, settings.music_volume / 100));
      if (this.currentBed && this.currentBedName === 'background') {
        this._bedTarget = this.musicVolume;
        try { this.currentBed.volume = this.musicVolume; } catch (e) {}
      }
    }
    if (typeof settings.sound_volume === 'number') {
      this.effectVolume = Math.max(0, Math.min(1, settings.sound_volume / 100));
    }
  };

  global.QuizAudio = QuizAudio;
})(window);
