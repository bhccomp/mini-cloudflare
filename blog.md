# FirePhage Blog Writing Instructions

These instructions exist to make every blog post feel like it was written by a working security engineer who built FirePhage — not by a content team. Follow them every time.

---

## Who is writing these posts

The author is Nikola Jocic. He has worked in malware analysis, cybersecurity, Linux administration, DevOps, and PHP development. He built FirePhage himself. He has seen real WordPress infections, real attack logs, and real client situations. That background must come through in the writing — not as a biography, but as the natural authority of someone who has actually dealt with these problems.

---

## The single most important rule

Every post must contain at least one thing only Nikola could write.

This means one of the following must appear somewhere in the post:

- A specific observation from real experience ("In practice, most XML-RPC abuse we see isn't a flood — it's a slow background drip that operators don't notice until PHP workers start queuing")
- A counterintuitive opinion with a reason behind it ("Disabling XML-RPC entirely is almost always the right call. The list of sites that genuinely still need it is very short.")
- A concrete example with a detail in it ("A WooCommerce store running 400 products can lose meaningful checkout capacity to bot pressure before any alarm fires — because the damage is CPU burn, not a 404 page")
- A honest limitation or tradeoff ("Rate limiting alone won't stop a distributed brute-force. It slows it down. That's still worth doing, but don't confuse slowing with stopping.")

If the post has none of these, it is not ready to publish. Add one before it goes live.

---

## Structure rules

**Opening — no throat clearing**

Do not start with a definition, a generic statement about how important security is, or a sentence that could apply to any blog on the internet.

Bad: "WordPress security is more important than ever in today's threat landscape."
Bad: "XML-RPC is a feature in WordPress that has been around since early versions."
Good: "Most WordPress sites that get hit through XML-RPC weren't ignoring security. They just forgot that endpoint existed."

The opening sentence should make someone who already knows WordPress security want to keep reading. It should feel like the start of a conversation, not a Wikipedia entry.

**Headings — specific, not categorical**

Bad headings: "Why This Matters", "Best Practices", "Final Thoughts", "What You Should Do"
Good headings: "Why disabling it is usually better than filtering it", "What XML-RPC abuse actually looks like in logs", "The case for putting this at the edge instead of inside WordPress"

Headings should tell you what position or finding the section contains — not just announce the topic.

**Body paragraphs — vary the rhythm**

Do not write every paragraph as: short declarative sentence, then 2-3 sentence explanation, then repeat.

Mix it up deliberately:
- Occasionally start a paragraph with a question
- Use a single short sentence as its own paragraph when something deserves emphasis
- Let a paragraph run longer when an idea actually needs the space
- Use "I" or "we" occasionally when sharing a direct observation

**Bullet lists — use sparingly**

Bullets are for genuinely enumerable things (a checklist, a set of options, a decision tree). They are not for breaking up every explanation into fragments.

If you find yourself writing bullets just to avoid writing a paragraph, write the paragraph instead. Paragraphs are harder to skim but they show reasoning. Reasoning is what builds trust.

**Ending — no summary recap**

Do not end every post with a section that recaps the entire article in bullet form. Do not write "Final Thoughts" or "Final Takeaway" as the last heading.

End with one of these instead:
- A single direct sentence that is the actual point of the whole post
- An honest admission of a limitation or edge case
- A forward-looking observation about where this attack surface is heading
- A specific recommendation with a reason, not a generic call to action

The FirePhage mention at the end should be one sentence, natural, and specific to what was discussed in the post. Not a boilerplate product pitch.

---

## Voice and tone

**Write shorter sentences than feels natural for AI.** Long compound sentences with multiple clauses connected by "and" or "but" are a strong AI signal. Break them up.

**Use concrete numbers and specifics when possible.** "A lot of traffic" is weak. "Hundreds of POST requests per hour to /xmlrpc.php" is real.

**Have an opinion.** Do not present every approach as equally valid. Nikola has seen what works. The post should reflect that.

**Do not over-explain things the audience already knows.** The reader is a WordPress site owner, agency developer, or system administrator. They know what a DNS record is. They know what PHP is. Skip the basics unless there is a specific non-obvious point to make.

**Avoid these phrases entirely:**
- "it is worth noting"
- "it is important to understand"
- "in today's threat landscape"
- "at the end of the day"
- "this is where many teams go wrong"
- "the good news is"
- "the reality is"
- "make no mistake"
- "that said"
- "in other words"
- any sentence that starts with "This means that"
- any sentence that starts with "That is why"

These are filler. They add words without adding meaning.

---

## SEO requirements

- Target one specific search phrase per post. Choose it before writing. Write for that phrase naturally — do not stuff it.
- The H1 title should match or closely reflect the search phrase someone would actually type.
- Use real secondary terms that belong in the topic naturally — do not force them.
- Aim for 1000–1800 words. Long enough to be substantive, short enough to stay focused.
- Use one internal link to a relevant FirePhage service page per post where it fits naturally.
- The meta description should be a single honest sentence about what the post actually covers — not a marketing tagline.

---

## Post checklist before publishing

- [ ] Opening sentence would work as a standalone tweet — specific and interesting
- [ ] At least one paragraph contains a real observation, opinion, or example only Nikola could write
- [ ] No heading is a generic category label
- [ ] No paragraph follows the same short-declarative-then-explanation pattern three times in a row
- [ ] Banned phrases are not present
- [ ] Bullets are used for genuinely list-like content only
- [ ] Ending is a single strong point, not a recap
- [ ] Author is set to Nikola Jocic, not Admin
- [ ] Internal link to one service page is present
- [ ] Internal link appears where it is actually relevant to the point being made, not just dropped into the post to satisfy linking
- [ ] Word count is between 1000 and 1800

---

## What to give Codex each time

When generating a new post, provide:

1. The target search phrase
2. The main point or position the post should argue (not just the topic — the *take*)
3. One real observation, example, or opinion from Nikola's experience to include verbatim or near-verbatim
4. The FirePhage service page most relevant to the topic (for the internal link)

Without item 3, the post will be generic. That one real input is what separates a post that ranks and builds trust from one that just exists.
