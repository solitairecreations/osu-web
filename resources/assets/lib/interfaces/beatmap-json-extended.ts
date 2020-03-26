// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

import BeatmapJSON from './beatmap-json';

// TODO: incomplete
export default interface BeatmapJSONExtended extends BeatmapJSON {
  accuracy: number;
  ar: number;
  beatmapset_id: number;
  convert: boolean | null;
  count_circles: number;
  count_sliders: number;
  count_spinners: number;
  count_total: number;
  cs: number;
  deleted_at: string | null;
  drain: number;
  failtimes?: BeatmapFailTimesArray;
  hit_length: number;
  last_updated: string;
  mode_int: number;
  passcount: number;
  playcount: number;
  ranked: number;
  status: string;
  total_length: number;
  url: string;
}
